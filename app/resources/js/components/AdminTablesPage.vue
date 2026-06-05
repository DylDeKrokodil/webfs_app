<script setup>
import { computed, onMounted, ref } from 'vue';
import { toastService } from '../services/toastService';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const tables = ref([]);
const selectedTableCode = ref('');
const isLoading = ref(true);
const isCheckingOut = ref(false);
const errorMessage = ref('');

const formatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

const dateFormatter = new Intl.DateTimeFormat('nl-NL', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const selectedTable = computed(() =>
    tables.value.find((table) => table.table_code === selectedTableCode.value) ?? tables.value[0] ?? null,
);

const totalOpenAmount = computed(() =>
    tables.value.reduce((sum, table) => sum + Number(table.total), 0),
);

const totalOpenItems = computed(() =>
    tables.value.reduce((sum, table) => sum + Number(table.items_count), 0),
);

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    return dateFormatter.format(new Date(value));
};

const loadTables = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch('/api/admin/table-receipts', {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'De actieve tafels konden niet worden geladen.');
        }

        tables.value = payload.tables ?? [];

        if (
            tables.value.length > 0 &&
            !tables.value.some((table) => table.table_code === selectedTableCode.value)
        ) {
            selectedTableCode.value = tables.value[0].table_code;
        }
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'De actieve tafels konden niet worden geladen.';
        toastService.error(errorMessage.value, { title: 'Tafels' });
    } finally {
        isLoading.value = false;
    }
};

const checkoutSelectedTable = async () => {
    if (!selectedTable.value) {
        return;
    }

    isCheckingOut.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(
            `/api/admin/table-receipts/${encodeURIComponent(selectedTable.value.table_code)}/checkout`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Afrekenen en PDF maken mislukt.');
        }

        if (payload.receipt_url) {
            window.open(payload.receipt_url, '_blank', 'noopener');
        }

        toastService.success(payload.message || 'De rekening is als PDF gemaakt.', {
            title: `Tafel ${selectedTable.value.table_code}`,
        });

        await loadTables();
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'Afrekenen en PDF maken mislukt.';
        toastService.error(errorMessage.value, { title: 'PDF maken mislukt' });
    } finally {
        isCheckingOut.value = false;
    }
};

onMounted(loadTables);
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
                <a href="/admin/kassa">Kassa</a>
                <a class="is-active" href="/admin/tafels">Tafels</a>
            </nav>

            <form class="admin-logout" action="/logout" method="POST">
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit">Log uit</button>
            </form>
        </aside>

        <section class="admin-workspace">
            <header class="admin-header">
                <div>
                    <p>US-4</p>
                    <h1>Actieve tafels en PDF rekening</h1>
                </div>
                <button class="admin-primary-action" type="button" @click="loadTables">
                    Ververs
                </button>
            </header>

            <p v-if="errorMessage" class="admin-error">{{ errorMessage }}</p>

            <section class="admin-metrics" aria-label="Openstaande tafel samenvatting">
                <div>
                    <span>Actieve tafels</span>
                    <strong>{{ tables.length }}</strong>
                </div>
                <div>
                    <span>Open items</span>
                    <strong>{{ totalOpenItems }}</strong>
                </div>
                <div>
                    <span>Open bedrag</span>
                    <strong>{{ formatter.format(totalOpenAmount) }}</strong>
                </div>
                <div>
                    <span>Geselecteerd</span>
                    <strong>{{ selectedTable ? `Tafel ${selectedTable.table_code}` : '-' }}</strong>
                </div>
            </section>

            <div class="admin-tables-layout">
                <section class="admin-list-panel" aria-labelledby="active-tables-title">
                    <div class="admin-panel-header">
                        <div>
                            <h2 id="active-tables-title">Niet betaalde tafels</h2>
                            <p>{{ tables.length }} tafels met openstaande tabletbestellingen</p>
                        </div>
                    </div>

                    <p v-if="isLoading" class="admin-empty-state">Actieve tafels laden...</p>
                    <p v-else-if="tables.length === 0" class="admin-empty-state">
                        Er zijn geen openstaande tafels.
                    </p>

                    <div v-else class="admin-table-list">
                        <button
                            v-for="table in tables"
                            :key="table.table_code"
                            class="admin-table-row"
                            :class="{ 'is-selected': selectedTable?.table_code === table.table_code }"
                            type="button"
                            @click="selectedTableCode = table.table_code"
                        >
                            <span class="admin-table-number">Tafel {{ table.table_code }}</span>
                            <span>
                                {{ table.orders_count }} rondes · {{ table.items_count }} items
                            </span>
                            <span>{{ formatDate(table.first_order_at) }}</span>
                            <strong>{{ formatter.format(table.total) }}</strong>
                        </button>
                    </div>
                </section>

                <aside class="admin-order-panel" aria-labelledby="receipt-title">
                    <div class="admin-panel-header">
                        <div>
                            <h2 id="receipt-title">Rekening</h2>
                            <p v-if="selectedTable">
                                Tafel {{ selectedTable.table_code }} · laatste ronde {{ formatDate(selectedTable.last_order_at) }}
                            </p>
                            <p v-else>Geen tafel geselecteerd</p>
                        </div>
                    </div>

                    <p v-if="!selectedTable" class="admin-empty-state">
                        Selecteer een actieve tafel.
                    </p>

                    <template v-else>
                        <div class="admin-receipt-lines">
                            <article
                                v-for="line in selectedTable.lines"
                                :key="`${line.menu_item_id}-${line.unit_price}`"
                                class="admin-receipt-line"
                            >
                                <span class="admin-receipt-thumb">{{ line.display_number || 'GD' }}</span>
                                <div>
                                    <strong>{{ line.name }}</strong>
                                    <small>
                                        {{ formatter.format(line.unit_price) }} per stuk
                                    </small>
                                </div>
                                <span>{{ line.quantity }}x</span>
                                <strong>{{ formatter.format(line.line_total) }}</strong>
                            </article>
                        </div>

                        <footer class="admin-order-total">
                            <span>Totaal</span>
                            <strong>{{ formatter.format(selectedTable.total) }}</strong>
                        </footer>

                        <button
                            class="admin-checkout"
                            type="button"
                            :disabled="isCheckingOut"
                            @click="checkoutSelectedTable"
                        >
                            {{ isCheckingOut ? 'PDF maken...' : 'Rekening opslaan als PDF' }}
                        </button>
                    </template>
                </aside>
            </div>
        </section>
    </main>
</template>
