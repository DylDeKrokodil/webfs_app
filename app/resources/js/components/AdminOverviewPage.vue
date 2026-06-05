<script setup>
import { computed, onMounted, ref } from 'vue';
import { toastService } from '../services/toastService';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const formatInputDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const currentWeek = (() => {
    const start = new Date();
    const day = start.getDay() || 7;
    start.setDate(start.getDate() - day + 1);

    const end = new Date(start);
    end.setDate(start.getDate() + 6);

    return {
        start: formatInputDate(start),
        end: formatInputDate(end),
    };
})();

const startDate = ref(currentWeek.start);
const endDate = ref(currentWeek.end);
const lines = ref([]);
const summary = ref({
    lines_count: 0,
    items_count: 0,
    total: 0,
    gross_total: 0,
    vat_amount: 0,
    vat_percentage: 9,
});
const isLoading = ref(false);
const hasLoaded = ref(false);
const activePreset = ref('this_week');

const formatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

const dateFormatter = new Intl.DateTimeFormat('nl-NL', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

const periodLabel = computed(() => {
    if (startDate.value === endDate.value) return startDate.value;

    return `${startDate.value} t/m ${endDate.value}`;
});

const getWeekRange = (date) => {
    const start = new Date(date);
    const day = start.getDay() || 7;
    start.setDate(start.getDate() - day + 1);

    const end = new Date(start);
    end.setDate(start.getDate() + 6);

    return { start, end };
};

const periodPresets = [
    {
        key: 'this_week',
        label: 'Deze week',
        range: () => getWeekRange(new Date()),
    },
    {
        key: 'last_week',
        label: 'Vorige week',
        range: () => {
            const date = new Date();
            date.setDate(date.getDate() - 7);

            return getWeekRange(date);
        },
    },
    {
        key: 'this_month',
        label: 'Deze maand',
        range: () => {
            const now = new Date();

            return {
                start: new Date(now.getFullYear(), now.getMonth(), 1),
                end: new Date(now.getFullYear(), now.getMonth() + 1, 0),
            };
        },
    },
    {
        key: 'last_month',
        label: 'Vorige maand',
        range: () => {
            const now = new Date();

            return {
                start: new Date(now.getFullYear(), now.getMonth() - 1, 1),
                end: new Date(now.getFullYear(), now.getMonth(), 0),
            };
        },
    },
    {
        key: 'this_year',
        label: 'Dit jaar',
        range: () => {
            const now = new Date();

            return {
                start: new Date(now.getFullYear(), 0, 1),
                end: new Date(now.getFullYear(), 11, 31),
            };
        },
    },
    {
        key: 'last_year',
        label: 'Vorig jaar',
        range: () => {
            const year = new Date().getFullYear() - 1;

            return {
                start: new Date(year, 0, 1),
                end: new Date(year, 11, 31),
            };
        },
    },
];

const applyPreset = async (preset) => {
    const range = preset.range();
    startDate.value = formatInputDate(range.start);
    endDate.value = formatInputDate(range.end);
    activePreset.value = preset.key;
    await loadOverview();
};

const markCustomPeriod = () => {
    activePreset.value = 'custom';
};

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
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const payload = await response.json();

        if (!response.ok) throw new Error(payload.message || 'Overzicht laden mislukt.');

        lines.value = payload.data ?? [];
        summary.value = payload.summary ?? {
            lines_count: 0,
            items_count: 0,
            total: 0,
            gross_total: 0,
            vat_amount: 0,
            vat_percentage: 9,
        };
        hasLoaded.value = true;
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isLoading.value = false;
    }
};

const formatDate = (value) => {
    if (!value) return '-';

    return dateFormatter.format(new Date(value));
};

const sourceLabel = (line) => {
    if (line.channel === 'tablet' && line.table_code) return `Tafel ${line.table_code}`;
    if (line.channel === 'takeaway') return 'Kassa';

    return line.channel || '-';
};

onMounted(loadOverview);
</script>

<template>
    <main class="min-h-screen bg-brand-light text-brand-dark flex font-sans antialiased">
        <!-- Sidebar -->
        <aside class="w-56 bg-brand-dark text-white flex flex-col sticky top-0 h-screen shadow-xl z-50">
            <div class="p-5 border-b border-white/5">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-brand-red rounded-lg flex items-center justify-center border border-white/10">
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
                <a href="/admin/tafels" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs text-stone-400 hover:bg-white/5 hover:text-white transition-all">
                    <span>Tafels</span>
                </a>
                <a href="/admin/overzicht" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs bg-white/10 text-white shadow-inner">
                    <span>Overzicht</span>
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
                    <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Rapportage</p>
                    <h1 class="text-xl font-black leading-tight">Overzicht</h1>
                </div>
                <div class="text-right hidden sm:block">
                    <p class="text-[9px] uppercase font-bold text-stone-400 tracking-widest">Periode</p>
                    <p class="text-xs font-black text-stone-900">{{ periodLabel }}</p>
                </div>
            </header>

            <div class="flex-1 p-6 overflow-y-auto">
                <section class="bg-white border border-brand-border rounded-2xl shadow-sm mb-6 overflow-hidden">
                    <div class="p-4 border-b border-stone-100 bg-brand-light/50">
                        <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Periode kiezen</h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="preset in periodPresets"
                                :key="preset.key"
                                type="button"
                                @click="applyPreset(preset)"
                                class="h-8 px-3 rounded-lg border text-[9px] font-black uppercase tracking-widest transition-all"
                                :class="activePreset === preset.key ? 'bg-brand-gold border-brand-gold text-white shadow-sm' : 'bg-white border-stone-200 text-stone-500 hover:border-brand-gold hover:text-stone-900'"
                            >
                                {{ preset.label }}
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-3 items-end">
                            <label class="space-y-1.5">
                                <span class="block text-[9px] uppercase font-black tracking-widest text-stone-400">Begindatum</span>
                                <input v-model="startDate" @change="markCustomPeriod" type="date" class="w-full h-10 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                            </label>
                            <label class="space-y-1.5">
                                <span class="block text-[9px] uppercase font-black tracking-widest text-stone-400">Einddatum</span>
                                <input v-model="endDate" @change="markCustomPeriod" type="date" class="w-full h-10 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                            </label>
                            <button
                                @click="loadOverview"
                                :disabled="isLoading"
                                class="h-10 px-5 bg-brand-gold text-white rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-[#854d03] active:scale-[0.98] transition-all shadow-md disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <span v-if="isLoading" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                <span>Maak overzicht</span>
                            </button>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white border border-brand-border p-4 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-400 tracking-wider mb-1">Regels</span>
                        <strong class="block text-lg font-black text-stone-900">{{ summary.lines_count }}</strong>
                    </div>
                    <div class="bg-white border border-brand-border p-4 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-400 tracking-wider mb-1">Aantal</span>
                        <strong class="block text-lg font-black text-stone-900">{{ summary.items_count }}</strong>
                    </div>
                    <div class="bg-white border border-brand-border p-4 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-400 tracking-wider mb-1">Omzet excl. btw</span>
                        <strong class="block text-lg font-black text-brand-red">{{ formatter.format(summary.total) }}</strong>
                    </div>
                    <div class="bg-white border border-brand-border p-4 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-400 tracking-wider mb-1">BTW {{ summary.vat_percentage }}%</span>
                        <strong class="block text-lg font-black text-stone-900">{{ formatter.format(summary.vat_amount) }}</strong>
                    </div>
                </div>

                <section class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-stone-100 bg-brand-light/50 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Orderregels</h3>
                            <p class="text-[10px] font-bold text-stone-400">Regels tonen kassabedragen; omzet hierboven is exclusief {{ summary.vat_percentage }}% btw</p>
                        </div>
                        <span class="text-[10px] font-bold text-stone-400">{{ lines.length }} resultaten</span>
                    </div>

                    <div v-if="isLoading" class="p-12 text-center">
                        <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                    </div>
                    <div v-else-if="hasLoaded && lines.length === 0" class="p-12 text-center">
                        <p class="font-black text-stone-300 italic text-sm">Geen afgerekende orderregels in deze periode</p>
                    </div>
                    <div v-else class="overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[760px] text-left">
                            <thead class="bg-stone-50 border-b border-stone-100">
                                <tr>
                                    <th class="px-5 py-3 text-[9px] uppercase tracking-widest font-black text-stone-400">Datum</th>
                                    <th class="px-5 py-3 text-[9px] uppercase tracking-widest font-black text-stone-400">Gerecht</th>
                                    <th class="px-5 py-3 text-[9px] uppercase tracking-widest font-black text-stone-400">Bron</th>
                                    <th class="px-5 py-3 text-[9px] uppercase tracking-widest font-black text-stone-400 text-right">Prijs</th>
                                    <th class="px-5 py-3 text-[9px] uppercase tracking-widest font-black text-stone-400 text-right">Aantal</th>
                                    <th class="px-5 py-3 text-[9px] uppercase tracking-widest font-black text-stone-400 text-right">Subtotaal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <tr v-for="line in lines" :key="line.id" class="hover:bg-[#FFF7ED] transition-colors">
                                    <td class="px-5 py-3 whitespace-nowrap text-xs font-bold text-stone-600">{{ formatDate(line.date) }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-8 h-8 bg-stone-100 rounded-lg flex items-center justify-center font-black text-[9px] text-stone-400 border border-stone-200 flex-shrink-0">
                                                {{ line.display_number || 'GD' }}
                                            </span>
                                            <span class="font-black text-xs text-stone-900 leading-tight">{{ line.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 bg-stone-100 text-stone-600 rounded text-[9px] font-black uppercase tracking-tight">{{ sourceLabel(line) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap text-xs font-bold text-stone-600">{{ formatter.format(line.unit_price) }}</td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap text-xs font-black text-stone-900">{{ line.quantity }}</td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap text-xs font-black text-brand-red">{{ formatter.format(line.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
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
