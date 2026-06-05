<script setup>
import { computed, onMounted, ref } from 'vue';
import { toastService } from '../services/toastService';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const params = new URLSearchParams(window.location.search);
const pathTableNumber = window.location.pathname.match(/^\/tablet\/(\d+)$/)?.[1] ?? '';
const rawTableNumber = pathTableNumber || params.get('tafel') || params.get('table') || '';

const tableNumber = computed(() => {
    const value = rawTableNumber.trim();

    if (!/^[1-9]\d*$/.test(value)) {
        return null;
    }

    const parsed = Number(value);

    return parsed <= 999 ? parsed : null;
});

const items = ref([]);
const orderLines = ref([]);
const orderHistory = ref([]);
const tableStatus = ref(null);
const isLoading = ref(true);
const isHistoryLoading = ref(false);
const isSubmitting = ref(false);
const repeatedSourceOrderId = ref(null);
const errorMessage = ref('');
const orderMessage = ref('');

const formatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

const groupedItems = computed(() => {
    const groups = new Map();

    items.value.forEach((item) => {
        if (!groups.has(item.category)) {
            groups.set(item.category, []);
        }

        groups.get(item.category).push(item);
    });

    return Array.from(groups, ([category, groupItems]) => ({ category, items: groupItems }));
});

const lineCount = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity, 0));
const orderTotal = computed(() =>
    orderLines.value.reduce((sum, line) => sum + line.quantity * Number(line.price), 0),
);
const canOrder = computed(() => tableStatus.value?.can_order ?? true);
const cooldownMinutes = computed(() => {
    const seconds = tableStatus.value?.cooldown_seconds ?? 0;

    return Math.max(1, Math.ceil(seconds / 60));
});

const formatOrderDate = (value) => {
    if (!value) {
        return 'Onbekend tijdstip';
    }

    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const loadTableStatus = async () => {
    if (!tableNumber.value) {
        return;
    }

    const response = await fetch(`/api/tablet/tables/${tableNumber.value}/status`, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    });

    if (!response.ok) {
        throw new Error('De tafelstatus kon niet worden geladen.');
    }

    const payload = await response.json();
    tableStatus.value = payload.data ?? null;
};

const loadOrderHistory = async () => {
    if (!tableNumber.value) {
        return;
    }

    isHistoryLoading.value = true;

    try {
        const response = await fetch(`/api/tablet/tables/${tableNumber.value}/history`, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            throw new Error('De bestelgeschiedenis kon niet worden geladen.');
        }

        const payload = await response.json();
        orderHistory.value = payload.data ?? [];
    } finally {
        isHistoryLoading.value = false;
    }
};

const loadTabletData = async () => {
    if (!tableNumber.value) {
        isLoading.value = false;
        errorMessage.value = 'Geef een geldig tafelnummer mee, bijvoorbeeld /tablet/1.';
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        await Promise.all([loadTableStatus(), loadOrderHistory()]);

        const response = await fetch('/api/menu-items', {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            throw new Error('De menukaart kon niet worden geladen.');
        }

        const payload = await response.json();
        items.value = payload.data ?? [];
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'De menukaart kon niet worden geladen.';
        toastService.error(errorMessage.value, { title: 'Tablet' });
    } finally {
        isLoading.value = false;
    }
};

const addOrderLine = (item, quantity = 1) => {
    const existingLine = orderLines.value.find((line) => line.id === item.id);

    if (existingLine) {
        existingLine.quantity += quantity;
        return;
    }

    orderLines.value.push({
        id: item.id,
        display_number: item.display_number,
        name: item.name,
        price: Number(item.price ?? item.current_price),
        quantity,
    });
};

const addItem = (item) => {
    addOrderLine(item);
};

const repeatOrder = (order) => {
    const activeLines = order.lines.filter((line) => line.is_active);

    orderLines.value = activeLines.map((line) => ({
        id: line.menu_item_id,
        display_number: line.display_number,
        name: line.name,
        price: Number(line.current_price),
        quantity: line.quantity,
    }));
    repeatedSourceOrderId.value = order.id;
};

const repeatHistoryLine = (order, line) => {
    if (!line.is_active) {
        return;
    }

    const currentSourceOrderId = repeatedSourceOrderId.value;

    addOrderLine(
        {
            id: line.menu_item_id,
            display_number: line.display_number,
            name: line.name,
            price: Number(line.current_price),
        },
        line.quantity,
    );

    repeatedSourceOrderId.value =
        orderLines.value.length === 1 || currentSourceOrderId === order.id ? order.id : null;
};

const decreaseQuantity = (line) => {
    if (line.quantity <= 1) {
        orderLines.value = orderLines.value.filter((currentLine) => currentLine.id !== line.id);
        return;
    }

    line.quantity -= 1;
};

const increaseQuantity = (line) => {
    line.quantity += 1;
};

const clearOrder = () => {
    orderLines.value = [];
    repeatedSourceOrderId.value = null;
};

const submitOrder = async () => {
    errorMessage.value = '';
    orderMessage.value = '';

    if (!tableNumber.value) {
        errorMessage.value = 'Er is geen geldig tafelnummer gekoppeld aan deze tablet.';
        toastService.error(errorMessage.value, { title: 'Bestellen' });
        return;
    }

    if (orderLines.value.length === 0) {
        errorMessage.value = 'Kies eerst een of meer gerechten.';
        toastService.error(errorMessage.value, { title: 'Bestellen' });
        return;
    }

    if (!canOrder.value) {
        errorMessage.value = tableStatus.value?.message ?? 'Deze tafel kan nu niet bestellen.';
        toastService.error(errorMessage.value, { title: `Tafel ${tableNumber.value}` });
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await fetch('/api/tablet/orders', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                table_number: tableNumber.value,
                source_order_id: repeatedSourceOrderId.value,
                lines: orderLines.value.map((line) => ({
                    menu_item_id: line.id,
                    quantity: line.quantity,
                })),
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (payload.status) {
                tableStatus.value = payload.status;
            }

            throw new Error(payload.message || 'Bestelling plaatsen mislukt.');
        }

        orderMessage.value = `Bestelling #${payload.order.id} is doorgestuurd naar de keuken.`;
        toastService.success(orderMessage.value, { title: `Tafel ${tableNumber.value}` });
        clearOrder();
        await Promise.all([loadTableStatus(), loadOrderHistory()]);
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Bestelling plaatsen mislukt.';
        toastService.error(errorMessage.value, { title: 'Bestellen mislukt' });
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(loadTabletData);
</script>

<template>
    <main class="tablet-app">
        <section class="tablet-shell">
            <header class="tablet-header">
                <div>
                    <p>Tablet bestellen</p>
                    <h1>
                        {{ tableNumber ? `Tafel ${tableNumber}` : 'Geen tafel gekoppeld' }}
                    </h1>
                </div>
                <strong>{{ formatter.format(orderTotal) }}</strong>
            </header>

            <p v-if="errorMessage" class="tablet-error">{{ errorMessage }}</p>
            <p v-if="orderMessage" class="tablet-notice">{{ orderMessage }}</p>

            <div v-if="tableNumber" class="tablet-layout">
                <section class="tablet-menu" aria-labelledby="tablet-menu-title">
                    <div v-if="tableStatus" class="tablet-status" :class="{ 'is-blocked': !canOrder }">
                        <div>
                            <span>Tafel</span>
                            <strong>{{ tableStatus.table_number }}</strong>
                        </div>
                        <div>
                            <span>Ronde</span>
                            <strong>{{ tableStatus.rounds_used }} / {{ tableStatus.max_rounds }}</strong>
                        </div>
                        <p>
                            <template v-if="canOrder">
                                Deze tafel kan bestellen.
                            </template>
                            <template v-else-if="tableStatus.cooldown_seconds > 0">
                                Nieuwe ronde mogelijk over {{ cooldownMinutes }} min.
                            </template>
                            <template v-else>
                                {{ tableStatus.message }}
                            </template>
                        </p>
                    </div>

                    <div class="tablet-panel-header">
                        <h2 id="tablet-menu-title">Menukaart</h2>
                        <span>{{ items.length }} gerechten</span>
                    </div>

                    <p v-if="isLoading" class="tablet-empty">Menukaart laden...</p>
                    <p v-else-if="items.length === 0" class="tablet-empty">Geen gerechten beschikbaar.</p>

                    <div v-else class="tablet-menu-groups">
                        <section
                            v-for="group in groupedItems"
                            :key="group.category"
                            class="tablet-menu-group"
                        >
                            <h3>{{ group.category }}</h3>
                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                class="tablet-menu-item"
                                type="button"
                                @click="addItem(item)"
                            >
                                <span class="tablet-item-code">{{ item.display_number || '-' }}</span>
                                <span class="tablet-item-copy">
                                    <strong>{{ item.name }}</strong>
                                    <small>{{ item.description || item.category }}</small>
                                </span>
                                <span class="tablet-item-price">{{ formatter.format(item.price) }}</span>
                            </button>
                        </section>
                    </div>
                </section>

                <aside class="tablet-order" aria-labelledby="tablet-order-title">
                    <section class="tablet-history" aria-labelledby="tablet-history-title">
                        <div class="tablet-panel-header">
                            <div>
                                <h2 id="tablet-history-title">Eerdere rondes</h2>
                                <span>{{ orderHistory.length }} bestellingen</span>
                            </div>
                        </div>

                        <p v-if="isHistoryLoading" class="tablet-empty">Geschiedenis laden...</p>
                        <p v-else-if="orderHistory.length === 0" class="tablet-empty">
                            Nog geen eerdere bestelling voor deze tafel.
                        </p>

                        <div v-else class="tablet-history-list">
                            <article
                                v-for="order in orderHistory"
                                :key="order.id"
                                class="tablet-history-order"
                            >
                                <header>
                                    <div>
                                        <strong>Bestelling #{{ order.id }}</strong>
                                        <span>{{ formatOrderDate(order.created_at) }}</span>
                                    </div>
                                    <button type="button" @click="repeatOrder(order)">
                                        Alles
                                    </button>
                                </header>

                                <button
                                    v-for="line in order.lines"
                                    :key="`${order.id}-${line.menu_item_id}`"
                                    class="tablet-history-line"
                                    type="button"
                                    :disabled="!line.is_active"
                                    @click="repeatHistoryLine(order, line)"
                                >
                                    <span>
                                        {{ line.quantity }}x {{ line.display_number || '-' }}
                                        {{ line.name }}
                                    </span>
                                    <small>
                                        <template v-if="line.is_active">
                                            {{ formatter.format(line.current_price) }}
                                        </template>
                                        <template v-else>
                                            Niet beschikbaar
                                        </template>
                                    </small>
                                </button>
                            </article>
                        </div>
                    </section>

                    <div class="tablet-panel-header">
                        <div>
                            <h2 id="tablet-order-title">Bestelling</h2>
                            <span>{{ lineCount }} items</span>
                        </div>
                        <button type="button" :disabled="orderLines.length === 0" @click="clearOrder">
                            Leeg
                        </button>
                    </div>

                    <p v-if="orderLines.length === 0" class="tablet-empty">
                        Tik op een gerecht om het toe te voegen.
                    </p>

                    <div v-else class="tablet-order-lines">
                        <article v-for="line in orderLines" :key="line.id" class="tablet-order-line">
                            <div>
                                <strong>{{ line.display_number || '-' }} {{ line.name }}</strong>
                                <small>{{ formatter.format(line.price) }} per stuk</small>
                            </div>
                            <div class="tablet-quantity">
                                <button type="button" @click="decreaseQuantity(line)">-</button>
                                <span>{{ line.quantity }}</span>
                                <button type="button" @click="increaseQuantity(line)">+</button>
                            </div>
                        </article>
                    </div>

                    <footer class="tablet-total">
                        <span>Totaal</span>
                        <strong>{{ formatter.format(orderTotal) }}</strong>
                    </footer>

                    <button
                        class="tablet-submit"
                        type="button"
                        :disabled="isSubmitting || orderLines.length === 0 || !canOrder"
                        @click="submitOrder"
                    >
                        {{ isSubmitting ? 'Doorsturen...' : 'Bestelling doorsturen' }}
                    </button>
                </aside>
            </div>
        </section>
    </main>
</template>
