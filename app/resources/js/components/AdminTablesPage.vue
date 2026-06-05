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
    if (!value) return '-';
    return dateFormatter.format(new Date(value));
};

const loadTables = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch('/api/admin/table-receipts', {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Laden mislukt.');
        tables.value = payload.tables ?? [];

        if (tables.value.length > 0 && !tables.value.some((table) => table.table_code === selectedTableCode.value)) {
            selectedTableCode.value = tables.value[0].table_code;
        }
    } catch (error) {
        errorMessage.value = error.message;
        toastService.error(errorMessage.value);
    } finally {
        isLoading.value = false;
    }
};

const checkoutSelectedTable = async () => {
    if (!selectedTable.value) return;
    isCheckingOut.value = true;
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
        if (!response.ok) throw new Error(payload.message || 'PDF maken mislukt.');

        if (payload.receipt_url) window.open(payload.receipt_url, '_blank', 'noopener');
        toastService.success(`Tafel ${selectedTable.value.table_code} afgerekend.`);
        await loadTables();
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isCheckingOut.value = false;
    }
};

onMounted(loadTables);
</script>

<template>
    <main class="min-h-screen bg-brand-light text-brand-dark flex font-sans antialiased">
        <!-- Sidebar - More Compact -->
        <aside class="w-56 bg-brand-dark text-white flex flex-col sticky top-0 h-screen shadow-xl z-50">
            <div class="p-5 border-b border-white/5">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-brand-red rounded-lg flex items-center justify-center shadow-lg border border-white/10">
                        <span class="text-white font-black text-sm">G</span>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Gouden Draak</p>
                        <h2 class="text-[10px] font-bold text-stone-500 uppercase">Admin</h2>
                    </div>
                </div>
            </div>

            <nav class="flex-1 p-3 space-y-1">
                <a href="/admin/menu" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs text-stone-400 hover:bg-white/5 hover:text-white transition-all">
                    <span>Menukaart</span>
                </a>
                <a href="/admin/kassa" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs text-stone-400 hover:bg-white/5 hover:text-white transition-all">
                    <span>Kassa</span>
                </a>
                <a href="/admin/tafels" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs bg-white/10 text-white shadow-inner">
                    <span>Tafels</span>
                </a>
            </nav>

            <form action="/logout" method="POST" class="p-3 border-t border-white/5">
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit" class="w-full py-2 rounded-lg font-black text-[9px] uppercase tracking-widest text-stone-500 hover:text-red-400 transition-colors">
                    Log uit
                </button>
            </form>
        </aside>

        <!-- Workspace -->
        <section class="flex-1 min-w-0 flex flex-col">
            <header class="bg-white border-b border-brand-border px-6 py-4 sticky top-0 z-40 flex items-center justify-between">
                <div>
                    <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Tafelbeheer</p>
                    <h1 class="text-xl font-black leading-tight">Actieve Tafels</h1>
                </div>
                <button @click="loadTables" class="px-4 py-2 bg-brand-light border border-brand-border rounded-xl font-black text-[9px] uppercase tracking-widest text-stone-600 hover:bg-stone-50 hover:border-stone-400 active:scale-[0.98] transition-all shadow-sm">
                    Verversen
                </button>
            </header>

            <div class="flex-1 p-6 overflow-y-auto">
                <!-- Metrics - More Compact -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div v-for="(val, label) in { 'Actieve Tafels': tables.length, 'Open Items': totalOpenItems, 'Open Bedrag': formatter.format(totalOpenAmount), 'Geselecteerd': selectedTable ? `Tafel ${selectedTable.table_code}` : '-' }" :key="label" class="bg-white border border-brand-border p-4 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-400 tracking-wider mb-1">{{ label }}</span>
                        <strong class="block text-lg font-black text-stone-900">{{ val }}</strong>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Tables List -->
                    <section class="lg:col-span-7 space-y-4">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col h-[calc(100dvh-240px)]">
                            <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                                <h3 class="font-black text-base text-stone-900">Wachtende Tafels</h3>
                                <p class="text-[10px] font-bold text-stone-400">Beheer openstaande rekeningen</p>
                            </div>

                            <div v-if="isLoading" class="p-12 text-center flex-1">
                                <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                            </div>
                            <div v-else-if="tables.length === 0" class="p-12 text-center space-y-2 flex-1">
                                <p class="font-black text-stone-300 italic text-sm">Geen openstaande bestellingen</p>
                            </div>
                            <div v-else class="divide-y divide-stone-100 flex-1 overflow-y-auto custom-scrollbar">
                                <button
                                    v-for="table in tables"
                                    :key="table.table_code"
                                    @click="selectedTableCode = table.table_code"
                                    class="w-full flex items-center gap-4 p-4 text-left transition-all hover:bg-brand-light"
                                    :class="selectedTable?.table_code === table.table_code ? 'bg-[#FFF7ED] ring-1 ring-inset ring-brand-gold/30' : ''"
                                >
                                    <div class="w-12 h-12 bg-brand-dark rounded-xl flex flex-col items-center justify-center text-white shadow-md">
                                        <span class="text-[8px] uppercase font-bold text-stone-500 mb-px">Tafel</span>
                                        <span class="text-base font-black leading-none">{{ table.table_code }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="px-1.5 py-0.5 bg-stone-100 text-stone-600 rounded text-[8px] font-black uppercase tracking-tighter">{{ table.orders_count }} RONDEN</span>
                                            <span class="px-1.5 py-0.5 bg-stone-100 text-stone-600 rounded text-[8px] font-black uppercase tracking-tighter">{{ table.items_count }} ITEMS</span>
                                        </div>
                                        <p class="text-[10px] font-bold text-stone-400">Eerste ronde: {{ formatDate(table.first_order_at) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-black text-lg text-brand-red leading-none mb-1">{{ formatter.format(table.total) }}</p>
                                        <p class="text-[8px] font-bold text-stone-400 uppercase tracking-widest">Openstaand</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Receipt Preview -->
                    <aside class="lg:col-span-5 sticky top-0 self-start mb-6">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-lg overflow-hidden flex flex-col h-[calc(100dvh-240px)]">
                            <div class="p-6 border-b border-stone-100 bg-brand-dark text-white flex-shrink-0">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-black text-lg leading-none">Rekening</h3>
                                        <p class="text-[9px] uppercase font-bold text-stone-500 mt-1 tracking-widest">Detailoverzicht</p>
                                    </div>
                                    <div v-if="selectedTable" class="px-3 py-1.5 bg-white/10 rounded-lg border border-white/10 text-center">
                                        <p class="text-[7px] uppercase font-bold text-stone-500">Tafel</p>
                                        <p class="text-base font-black leading-none">{{ selectedTable.table_code }}</p>
                                    </div>
                                </div>
                                <div v-if="selectedTable" class="flex gap-3">
                                    <div class="flex-1 p-2.5 bg-white/5 rounded-lg border border-white/5">
                                        <p class="text-[7px] uppercase font-bold text-stone-500">Inhoud</p>
                                        <p class="text-xs font-black">{{ selectedTable.items_count }} items</p>
                                    </div>
                                    <div class="flex-1 p-2.5 bg-white/5 rounded-lg border border-white/5 text-right">
                                        <p class="text-[7px] uppercase font-bold text-stone-500">Tijdstip</p>
                                        <p class="text-xs font-black">{{ formatDate(selectedTable.last_order_at).split(' ')[1] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!selectedTable" class="flex-1 p-12 text-center space-y-3">
                                <p class="text-stone-300 font-bold text-xs italic">Selecteer een tafel</p>
                            </div>
                            <template v-else>
                                <div class="flex-1 overflow-y-auto p-6 space-y-5 custom-scrollbar">
                                    <div v-for="line in selectedTable.lines" :key="`${line.menu_item_id}-${line.unit_price}`" class="flex items-start gap-3">
                                        <div class="w-8 h-8 bg-stone-100 rounded-lg flex items-center justify-center font-black text-[9px] text-stone-400 border border-stone-200 flex-shrink-0">
                                            {{ line.display_number || 'GD' }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start gap-2">
                                                <h4 class="font-black text-stone-900 text-xs leading-tight truncate">{{ line.name }}</h4>
                                                <span class="font-black text-stone-900 text-xs">{{ formatter.format(line.line_total) }}</span>
                                            </div>
                                            <p class="text-[9px] font-bold text-stone-400 mt-0.5">{{ line.quantity }}x {{ formatter.format(line.unit_price) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6 bg-brand-light border-t border-stone-100 space-y-5 flex-shrink-0">
                                    <div class="flex items-center justify-between">
                                        <span class="font-black text-stone-400 uppercase text-[9px] tracking-widest">Eindbedrag</span>
                                        <span class="font-black text-2xl text-brand-red">{{ formatter.format(selectedTable.total) }}</span>
                                    </div>

                                    <button
                                        @click="checkoutSelectedTable"
                                        :disabled="isCheckingOut"
                                        class="w-full h-12 bg-brand-gold text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-lg hover:bg-[#854d03] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                                    >
                                        <span v-if="isCheckingOut" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                        {{ isCheckingOut ? 'Bezig...' : 'Afrekenen (PDF)' }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap');

.font-sans {
    font-family: 'DM Sans', sans-serif;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--color-brand-border);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--color-brand-gold);
}
</style>
