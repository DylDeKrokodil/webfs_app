<script setup>
import { computed, onMounted, ref } from 'vue';
import { Line, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    ArcElement,
} from 'chart.js';
import { useAdminShell } from '../composables/useAdminShell';
import { usePeriodSelection } from '../composables/usePeriodSelection';
import { currencyFormatter as formatter } from '../services/formatters';
import { toastService } from '../services/toastService';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    ArcElement
);

const { csrfToken, isSidebarOpen, isCollapsed } = useAdminShell();
const {
    startDate,
    endDate,
    activePreset,
    periodPresets,
    applyPreset,
    markCustomPeriod,
} = usePeriodSelection();

const isLoading = ref(false);
const stats = ref({
    top_items: [],
    channels: [],
    trends: [],
    review_trends: [],
});

const handleApplyPreset = (preset) => applyPreset(preset, loadStats);

const loadStats = async () => {
    isLoading.value = true;
    try {
        const params = new URLSearchParams({ start_date: startDate.value, end_date: endDate.value });
        const response = await fetch(`/api/admin/stats?${params}`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!response.ok) throw new Error('Statistieken laden mislukt.');
        stats.value = await response.json();
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isLoading.value = false;
    }
};

const setupRealtime = () => {
    if (!window.Echo) return;

    window.Echo.channel('admin-notifications')
        .listen('.CheckoutCompleted', (data) => {
            console.log('Real-time: Stats Refresh', data);
            loadStats();
        });
};

onMounted(() => {
    loadStats();
    setupRealtime();
});

const trendChartData = computed(() => ({
    labels: stats.value.trends.map(t => t.date),
    datasets: [{
        label: 'Omzet (€)',
        data: stats.value.trends.map(t => t.total_revenue),
        borderColor: '#B91C1C',
        backgroundColor: 'rgba(185, 28, 28, 0.1)',
        tension: 0.3,
        fill: true,
    }]
}));

const reviewChartData = computed(() => ({
    labels: stats.value.review_trends.map(t => t.date),
    datasets: [{
        label: 'Gem. Score',
        data: stats.value.review_trends.map(t => t.avg_score),
        borderColor: '#D4AF37',
        backgroundColor: 'rgba(212, 175, 55, 0.1)',
        tension: 0.4,
        fill: true,
        spanGaps: true,
    }]
}));

const channelChartData = computed(() => ({
    labels: stats.value.channels.map(c => {
        if (c.channel === 'kassa') return 'Kassa';
        if (c.channel === 'web') return 'Website';
        if (c.channel === 'tablet') return 'Dine-in';
        return c.channel.charAt(0).toUpperCase() + c.channel.slice(1);
    }),
    datasets: [{
        data: stats.value.channels.map(c => c.total_revenue),
        backgroundColor: ['#B91C1C', '#D97706', '#1C1917', '#78716C'],
    }]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { weight: 'bold', size: 10 } } } }
};

const reviewChartOptions = {
    ...chartOptions,
    scales: {
        y: {
            min: 1,
            max: 5,
            ticks: {
                stepSize: 1
            }
        }
    }
};
</script>

<template>
    <section class="flex-1 min-w-0 min-h-0 flex flex-col">
        <header class="bg-white border-b border-brand-border px-6 py-4 z-40 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-4">
                <button @click="isSidebarOpen = true" class="lg:hidden p-2 -ml-2 text-stone-600 hover:bg-stone-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
                <div>
                    <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Inzicht</p>
                    <h1 class="text-xl font-black leading-tight">Statistieken</h1>
                </div>
            </div>
        </header>

        <div class="flex-1 min-h-0 flex flex-col">
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
                            @click="loadStats"
                            :disabled="isLoading"
                            class="h-10 px-6 bg-brand-gold text-white rounded-xl font-black text-[9px] uppercase tracking-widest shadow-md shadow-brand-gold/10 hover:bg-stone-800 transition-colors disabled:opacity-50"
                        >
                            Vernieuwen
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                <div v-if="isLoading" class="h-full flex items-center justify-center">
                    <div class="w-10 h-10 border-4 border-stone-100 border-t-brand-gold rounded-full animate-spin"></div>
                </div>

                <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-6 pb-6">
                    <!-- Omzet Trend -->
                    <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[400px]">
                        <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                            <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Omzet Trend</h3>
                        </div>
                        <div class="flex-1 min-h-0 p-6 flex items-center justify-center">
                            <Line v-if="stats.trends.some(t => t.total_revenue > 0)" :data="trendChartData" :options="chartOptions" />
                            <div v-else class="flex flex-col items-center justify-center text-center">
                                <div class="w-12 h-12 bg-stone-100 rounded-full flex items-center justify-center text-stone-400 mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                                </div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-stone-600">Geen omzet trend</p>
                                <p class="text-[11px] font-medium text-stone-500 mt-1">Er is geen omzet geregistreerd in deze periode.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Top Items -->
                    <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                            <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Populaire Gerechten</h3>
                        </div>
                        <div class="flex-1 overflow-auto custom-scrollbar">
                            <table v-if="stats.top_items.length > 0" class="w-full text-left">
                                <thead class="bg-stone-50 text-[9px] uppercase font-black text-stone-700 sticky top-0 shadow-sm">
                                    <tr>
                                        <th class="px-6 py-3">Gerecht</th>
                                        <th class="px-6 py-3 text-right">Aantal</th>
                                        <th class="px-6 py-3 text-right">Omzet</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    <tr v-for="item in stats.top_items" :key="item.name" class="hover:bg-brand-light/30 transition-colors">
                                        <td class="px-6 py-3">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-stone-900 leading-tight">{{ item.name }}</span>
                                                <span class="text-[9px] font-bold text-stone-600 uppercase tracking-tighter">{{ item.display_number }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-right text-xs font-black text-stone-700">{{ item.total_quantity }}</td>
                                        <td class="px-6 py-3 text-right text-xs font-black text-brand-red">{{ formatter.format(item.total_revenue) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="h-full min-h-[200px] flex flex-col items-center justify-center p-8 text-center">
                                <div class="w-12 h-12 bg-stone-100 rounded-full flex items-center justify-center text-stone-400 mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                                </div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-stone-600">Geen verkoopgegevens</p>
                                <p class="text-[11px] font-medium text-stone-500 mt-1">Er zijn nog geen gerechten verkocht in deze periode.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Channel Distribution -->
                    <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[350px]">
                        <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                            <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Omzet per Kanaal</h3>
                        </div>
                        <div class="flex-1 min-h-0 p-6 flex items-center justify-center">
                            <div v-if="stats.channels.length > 0" class="w-full h-full max-h-[250px]">
                                <Doughnut :data="channelChartData" :options="chartOptions" />
                            </div>
                            <div v-else class="flex flex-col items-center justify-center text-center">
                                <div class="w-12 h-12 bg-stone-100 rounded-full flex items-center justify-center text-stone-400 mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                                </div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-stone-600">Geen kanaal data</p>
                                <p class="text-[11px] font-medium text-stone-500 mt-1">Kies een andere periode om data te zien.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Review Trend -->
                    <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[350px]">
                        <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                            <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Klantwaardering Trend</h3>
                        </div>
                        <div class="flex-1 min-h-0 p-6 flex items-center justify-center">
                            <Line v-if="stats.review_trends.some(t => t.avg_score !== null)" :data="reviewChartData" :options="reviewChartOptions" />
                            <div v-else class="flex flex-col items-center justify-center text-center">
                                <div class="w-12 h-12 bg-stone-100 rounded-full flex items-center justify-center text-stone-400 mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17.75l-6.172 3.245 1.179-6.873-4.993-4.867 6.9-1.002L12 2l3.086 6.253 6.9 1.002-4.993 4.867 1.179 6.873z"/></svg>
                                </div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-stone-600">Geen recensies</p>
                                <p class="text-[11px] font-medium text-stone-500 mt-1">Er zijn nog geen recensies geplaatst in deze periode.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
</style>
