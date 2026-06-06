<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { useAdminShell } from '../composables/useAdminShell';
import { usePeriodSelection } from '../composables/usePeriodSelection';
import { currencyFormatter as formatter } from '../services/formatters';
import { toastService } from '../services/toastService';

const { csrfToken, isSidebarOpen, isCollapsed } = useAdminShell();
const {
    startDate,
    endDate,
    activePreset,
    periodLabel,
    periodPresets,
    applyPreset,
    markCustomPeriod,
} = usePeriodSelection();

const overview = ref(null);
const lines = ref([]);
const salesSummaries = ref([]);
const summary = ref({
    lines_count: 0,
    items_count: 0,
    total: 0,
    gross_total: 0,
    vat_amount: 0,
    vat_percentage: 9,
});
const isLoading = ref(false);
const isLoadingSummaries = ref(false);
const hasLoaded = ref(false);

const dateFormatter = new Intl.DateTimeFormat('nl-NL', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const handleApplyPreset = (preset) => applyPreset(preset, loadOverview);

const loadOverview = async () => {
    if (!startDate.value || !endDate.value) {
        toastService.error('Kies een begin- en einddatum.');
        return;
    }

    isLoading.value = true;

    try {
        const params = new URLSearchParams({
            start_date: startDate.value,
            end_date: endDate.value,
        });

        const response = await fetch(`/api/admin/order-line-overview?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            throw new Error('Laden mislukt.');
        }

        const data = await response.json();
        lines.value = data.data || [];
        summary.value = data.summary;
        hasLoaded.value = true;
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isLoading.value = false;
    }
};

const loadSalesSummaries = async () => {
    isLoadingSummaries.value = true;

    try {
        const response = await fetch('/api/admin/sales-summaries', {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            throw new Error('Laden mislukt.');
        }

        const data = await response.json();
        salesSummaries.value = data.data || [];
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isLoadingSummaries.value = false;
    }
};

const formatDate = (value) => {
    if (!value) return '-';

    return dateFormatter.format(new Date(value));
};

const formatReportDate = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
};

const sourceLabel = (line) => {
    if (line.channel === 'kassa') return 'Kassa';
    if (line.channel === 'web') return 'Website';
    if (line.channel === 'tablet' && line.table_code) return `Tafel ${line.table_code}`;

    return line.channel || '-';
};

const setupRealtime = () => {
    if (!window.Echo) return;

    window.Echo.channel('admin-notifications')
        .listen('.CheckoutCompleted', (data) => {
            console.log('Real-time: Checkout Completed', data);
            let source = data.channel;
            if (data.channel === 'kassa') source = 'Kassa';
            else if (data.channel === 'web') source = 'Website';
            else if (data.channel === 'tablet') source = `Tafel ${data.table_code}`;

            toastService.info(`Nieuwe betaling ontvangen: ${formatter.format(data.total)} (${source})`);
            loadOverview();
            loadSalesSummaries();
        });
};

onMounted(() => {
    loadOverview();
    loadSalesSummaries();
    setupRealtime();
});
</script>

<template>
    <main class="h-dvh overflow-hidden bg-brand-light text-brand-dark flex font-sans antialiased">
        <AdminSidebar
            :is-open="isSidebarOpen"
            :is-collapsed="isCollapsed"
            active-page="overzicht"
            :csrf-token="csrfToken"
            @close="isSidebarOpen = false"
            @toggle-collapse="isCollapsed = !isCollapsed"
        />

        <section class="flex-1 min-w-0 min-h-0 flex flex-col">
            <header class="bg-white border-b border-brand-border px-6 py-4 z-40 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-4">
                    <button @click="isSidebarOpen = true" class="lg:hidden p-2 -ml-2 text-stone-600 hover:bg-stone-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div>
                        <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Beheer</p>
                        <h1 class="text-xl font-black leading-tight">Overzicht</h1>
                    </div>
                </div>
            </header>

            <div class="flex-1 min-h-0 flex flex-col xl:flex-row">
                <!-- Sales List -->
                <div class="flex-1 min-h-0 flex flex-col border-r border-brand-border bg-white">
                    <!-- Filters -->
                    <div class="p-6 border-b border-brand-border flex-shrink-0 bg-white">
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="preset in periodPresets"
                                    :key="preset.key"
                                    type="button"
                                    @click="handleApplyPreset(preset)"
                                    class="h-8 px-3 rounded-lg border text-[9px] font-black uppercase tracking-widest transition-all"
                                    :class="activePreset === preset.key ? 'bg-brand-gold border-brand-gold text-white shadow-sm' : 'bg-white border-stone-200 text-stone-700 hover:border-brand-gold hover:text-stone-900'"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>

                            <div class="flex flex-wrap items-end gap-3">
                                <label class="flex-1 min-w-[140px] space-y-1">
                                    <span class="block text-[9px] uppercase font-black text-stone-700">Van</span>
                                    <input v-model="startDate" type="date" @change="markCustomPeriod" class="w-full h-10 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                </label>
                                <label class="flex-1 min-w-[140px] space-y-1">
                                    <span class="block text-[9px] uppercase font-black text-stone-700">Tot</span>
                                    <input v-model="endDate" type="date" @change="markCustomPeriod" class="w-full h-10 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                </label>
                                <button
                                    type="button"
                                    @click="loadOverview"
                                    :disabled="isLoading"
                                    class="h-10 px-6 bg-brand-gold text-white rounded-xl font-black text-[9px] uppercase tracking-widest shadow-md shadow-brand-gold/10 hover:bg-stone-800 transition-colors disabled:opacity-50"
                                >
                                    Filteren
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 border-b border-brand-border bg-stone-50/50">
                        <div class="p-6 border-r border-brand-border">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Aantal regels</span>
                            <p class="text-2xl font-black text-stone-900">{{ summary.lines_count }}</p>
                        </div>
                        <div class="p-6 border-r border-brand-border">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Items verkocht</span>
                            <p class="text-2xl font-black text-stone-900">{{ summary.items_count }}</p>
                        </div>
                        <div class="p-6 border-r border-brand-border">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Totaal (incl. BTW)</span>
                            <p class="text-2xl font-black text-brand-red">{{ formatter.format(summary.gross_total) }}</p>
                        </div>
                        <div class="p-6">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Totaal (excl. BTW)</span>
                            <p class="text-2xl font-black text-stone-900">{{ formatter.format(summary.total) }}</p>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="flex-1 overflow-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-white shadow-sm z-10">
                                <tr>
                                    <th class="px-6 py-4 text-[9px] uppercase font-black text-stone-700 border-b border-brand-border">Datum & Tijd</th>
                                    <th class="px-6 py-4 text-[9px] uppercase font-black text-stone-700 border-b border-brand-border">Gerecht</th>
                                    <th class="px-6 py-4 text-[9px] uppercase font-black text-stone-700 border-b border-brand-border text-center">Aantal</th>
                                    <th class="px-6 py-4 text-[9px] uppercase font-black text-stone-700 border-b border-brand-border">Bron</th>
                                    <th class="px-6 py-4 text-[9px] uppercase font-black text-stone-700 border-b border-brand-border text-right">Totaal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-border">
                                <tr v-for="line in lines" :key="line.id" class="group hover:bg-stone-50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-bold text-stone-600">{{ formatDate(line.date) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-stone-900 leading-tight">{{ line.name }}</span>
                                            <span class="text-[9px] font-bold text-stone-600 uppercase tracking-tighter">{{ line.display_number }} • {{ formatter.format(line.unit_price) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-stone-100 text-[10px] font-black text-stone-900">{{ line.quantity }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex px-2 py-1 rounded-md text-[8px] font-black uppercase tracking-widest"
                                            :class="{
                                                'bg-brand-red/10 text-brand-red': line.channel === 'kassa',
                                                'bg-brand-gold/10 text-brand-gold': line.channel === 'tablet',
                                                'bg-blue-500/10 text-blue-600': line.channel === 'web'
                                            }"
                                        >
                                            {{ sourceLabel(line) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-xs font-black text-stone-900">{{ formatter.format(line.line_total) }}</td>
                                </tr>
                                <tr v-if="lines.length === 0 && hasLoaded">
                                    <td colspan="5" class="px-6 py-20 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-stone-300 mb-4"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M12 12v6"/><path d="M9 15h6"/></svg>
                                            <p class="text-xs font-bold text-stone-400 uppercase tracking-widest">Geen verkoopregels gevonden</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Daily Summaries -->
                <aside class="w-full xl:w-96 min-h-0 flex flex-col bg-stone-50/30">
                    <header class="p-6 border-b border-brand-border flex items-center justify-between bg-white flex-shrink-0">
                        <div>
                            <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Rapportages</p>
                            <h2 class="text-lg font-black leading-tight">Dagrapporten</h2>
                        </div>
                        <div v-if="isLoadingSummaries" class="w-4 h-4 border-2 border-stone-200 border-t-brand-gold rounded-full animate-spin"></div>
                    </header>
                    <div class="flex-1 overflow-auto p-6 custom-scrollbar">
                        <div class="space-y-4 pb-6">
                            <a
                                v-for="summary in salesSummaries"
                                :key="summary.id"
                                :href="summary.download_url"
                                target="_blank"
                                class="flex flex-col gap-4 p-5 bg-white border border-brand-border rounded-2xl shadow-sm hover:shadow-md hover:border-brand-gold transition-all group"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-stone-50 flex items-center justify-center text-stone-500 group-hover:text-brand-gold transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-stone-900">{{ formatReportDate(summary.date) }}</span>
                                            <span class="text-[9px] font-bold text-stone-600 uppercase tracking-widest">{{ summary.orders_count }} bestellingen</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-black text-brand-red">{{ formatter.format(summary.gross_total) }}</p>
                                        <p class="text-[9px] font-bold text-stone-600 uppercase tracking-tighter">Totaal (incl. BTW)</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-4 border-t border-stone-50">
                                    <span class="text-[8px] font-black uppercase tracking-widest text-stone-600 group-hover:text-brand-gold transition-colors">Download PDF Rapport</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="text-stone-500 group-hover:text-brand-gold transition-all group-hover:translate-x-0.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                </div>
                            </a>

                            <div v-if="salesSummaries.length === 0 && !isLoadingSummaries" class="py-12 text-center">
                                <p class="text-[9px] font-black text-stone-500 uppercase tracking-widest">Nog geen rapporten beschikbaar</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--color-brand-border);
    border-radius: 10px;
}
</style>
