<script setup>
import { computed, onMounted, ref } from 'vue';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const categories = ref([]);
const items = ref([]);
const query = ref('');
const categoryFilter = ref('all');
const orderLines = ref([]);
const isLoading = ref(true);
const isCheckingOut = ref(false);
const checkoutMessage = ref('');
const errorMessage = ref('');

const formatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

const activeItems = computed(() => items.value.filter((item) => item.is_active));
const visibleItems = computed(() => {
    const needle = query.value.trim().toLowerCase();

    return activeItems.value.filter((item) => {
        const matchesQuery =
            needle === '' ||
            [item.display_number, item.name, item.category]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(needle));
        const matchesCategory =
            categoryFilter.value === 'all' ||
            String(item.menu_category_id) === String(categoryFilter.value);

        return matchesQuery && matchesCategory;
    });
});

const groupedItems = computed(() => {
    const groups = new Map();

    visibleItems.value.forEach((item) => {
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

const loadMenu = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch('/api/admin/menu-items', {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            throw new Error('De kassamenukaart kon niet worden geladen.');
        }

        const payload = await response.json();
        categories.value = (payload.categories ?? []).filter((category) => category.is_active);
        items.value = payload.items ?? [];
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'De kassamenukaart kon niet worden geladen.';
    } finally {
        isLoading.value = false;
    }
};

const addItem = (item) => {
    const existingLine = orderLines.value.find((line) => line.id === item.id);

    if (existingLine) {
        existingLine.quantity += 1;
        return;
    }

    orderLines.value.push({
        id: item.id,
        display_number: item.display_number,
        name: item.name,
        price: Number(item.price),
        quantity: 1,
    });
};

const increaseQuantity = (line) => {
    line.quantity += 1;
};

const decreaseQuantity = (line) => {
    if (line.quantity <= 1) {
        orderLines.value = orderLines.value.filter((currentLine) => currentLine.id !== line.id);
        return;
    }

    line.quantity -= 1;
};

const clearOrder = () => {
    orderLines.value = [];
};

const checkoutOrder = async () => {
    checkoutMessage.value = '';
    errorMessage.value = '';

    if (orderLines.value.length === 0) {
        errorMessage.value = 'Niets geselecteerd.';
        return;
    }

    isCheckingOut.value = true;

    try {
        const response = await fetch('/api/admin/orders', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                lines: orderLines.value.map((line) => ({
                    menu_item_id: line.id,
                    quantity: line.quantity,
                })),
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Afrekenen mislukt.');
        }

        checkoutMessage.value = `Verkoop succesvol. Bestelling #${payload.order.id} is afgerekend.`;
        clearOrder();
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Afrekenen mislukt.';
    } finally {
        isCheckingOut.value = false;
    }
};

onMounted(loadMenu);
</script>

<template>
    <main class="admin-app">
        <aside class="admin-sidebar" aria-label="Admin navigatie">
            <a class="admin-brand" href="/admin/menu">
                <span>GD</span>
                <strong>Gouden Draak</strong>
            </a>

            <nav class="admin-nav">
                <a href="/admin/menu">Menukaart</a>
                <a class="is-active" href="/admin/kassa">Kassa</a>
            </nav>

            <form class="admin-logout" action="/logout" method="POST">
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit">Log uit</button>
            </form>
        </aside>

        <section class="admin-workspace">
            <header class="admin-header">
                <div>
                    <p>US-9</p>
                    <h1>Kassa zoeken en filteren</h1>
                </div>
            </header>

            <p v-if="errorMessage" class="admin-error">{{ errorMessage }}</p>
            <p v-if="checkoutMessage" class="admin-notice">{{ checkoutMessage }}</p>

            <div class="admin-kassa-layout">
                <section class="admin-list-panel" aria-labelledby="kassa-menu-title">
                    <div class="admin-panel-header">
                        <div>
                            <h2 id="kassa-menu-title">Gerechten zoeken</h2>
                            <p>{{ visibleItems.length }} actieve gerechten gevonden</p>
                        </div>
                    </div>

                    <div class="admin-filters">
                        <input
                            v-model="query"
                            type="search"
                            placeholder="Zoek op gerechtnaam of gerechtnummer"
                            autofocus
                        >
                        <select v-model="categoryFilter">
                            <option value="all">Alle categorieen</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <p v-if="isLoading" class="admin-empty-state">Kassamenukaart laden...</p>
                    <p v-else-if="visibleItems.length === 0" class="admin-empty-state">
                        Geen gerechten gevonden.
                    </p>

                    <div v-else class="admin-menu-groups">
                        <section
                            v-for="group in groupedItems"
                            :key="group.category"
                            class="admin-menu-group"
                        >
                            <h3>{{ group.category }}</h3>
                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                class="admin-kassa-menu-row"
                                type="button"
                                @click="addItem(item)"
                            >
                                <span class="admin-menu-code">{{ item.display_number || '-' }}</span>
                                <span class="admin-menu-name">
                                    <strong>{{ item.name }}</strong>
                                    <small>{{ item.description || item.category }}</small>
                                </span>
                                <span class="admin-menu-price">{{ formatter.format(item.price) }}</span>
                                <span class="admin-add-label">Toevoegen</span>
                            </button>
                        </section>
                    </div>
                </section>

                <aside class="admin-order-panel" aria-labelledby="current-order-title">
                    <div class="admin-panel-header">
                        <div>
                            <h2 id="current-order-title">Huidige bestelling</h2>
                            <p>{{ lineCount }} items</p>
                        </div>
                        <button class="admin-danger" type="button" @click="clearOrder">
                            Leeg
                        </button>
                    </div>

                    <p v-if="orderLines.length === 0" class="admin-empty-state">
                        Voeg gerechten toe vanuit de zoekresultaten.
                    </p>

                    <div v-else class="admin-order-lines">
                        <article v-for="line in orderLines" :key="line.id" class="admin-order-line">
                            <div>
                                <strong>{{ line.display_number || '-' }} {{ line.name }}</strong>
                                <small>{{ formatter.format(line.price) }} per stuk</small>
                            </div>

                            <div class="admin-quantity-control" aria-label="Aantal wijzigen">
                                <button type="button" @click="decreaseQuantity(line)">-</button>
                                <span>{{ line.quantity }}</span>
                                <button type="button" @click="increaseQuantity(line)">+</button>
                            </div>

                            <strong>{{ formatter.format(line.quantity * line.price) }}</strong>
                        </article>
                    </div>

                    <footer class="admin-order-total">
                        <span>Totaal</span>
                        <strong>{{ formatter.format(orderTotal) }}</strong>
                    </footer>

                    <button
                        class="admin-checkout"
                        type="button"
                        :disabled="isCheckingOut || orderLines.length === 0"
                        @click="checkoutOrder"
                    >
                        {{ isCheckingOut ? 'Afrekenen...' : 'Afrekenen' }}
                    </button>
                </aside>
            </div>
        </section>
    </main>
</template>
