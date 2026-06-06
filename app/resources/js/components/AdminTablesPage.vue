<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { postRequest } from '../services/apiService';
import { useAdminShell } from '../composables/useAdminShell';
import { adminDateTimeFormatter as dateFormatter, currencyFormatter as formatter } from '../services/formatters';
import { toastService } from '../services/toastService';

const { csrfToken, isSidebarOpen, isCollapsed } = useAdminShell();

const tables = ref([]);
const assistanceRequests = ref([]);
const selectedTableCode = ref('');
const isLoading = ref(true);
const isCheckingOut = ref(false);
const resolvingAssistanceRequestId = ref(null);
const isRefreshing = ref(false);
const errorMessage = ref('');

const selectedTable = computed(() =>
    tables.value.find((table) => table.table_code === selectedTableCode.value) ?? tables.value[0] ?? null,
);

const totalOpenAmount = computed(() =>
    tables.value.reduce((sum, table) => sum + Number(table.total), 0),
);

const totalOpenItems = computed(() =>
    tables.value.reduce((sum, table) => sum + Number(table.items_count), 0),
);

const openAssistanceCount = computed(() => assistanceRequests.value.length);

const formatDate = (value) => {
    if (!value) return '-';
    return dateFormatter.format(new Date(value));
};

const loadTables = async (silent = false) => {
    console.log(`Loading tables (silent: ${silent})...`);
    if (!silent) isLoading.value = true;
    isRefreshing.value = true;
    errorMessage.value = '';

    try {
        const [tableResponse, assistanceResponse] = await Promise.all([
            fetch('/api/admin/table-receipts', {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            }),
            fetch('/api/admin/table-assistance-requests', {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            }),
        ]);

        const tablePayload = await tableResponse.json().catch(() => ({}));
        if (!tableResponse.ok) throw new Error(tablePayload.message || 'Tafels laden mislukt.');
        
        console.log('Tables fetched:', tablePayload.tables?.length ?? 0);
        tables.value = tablePayload.tables ?? [];

        const assistancePayload = await assistanceResponse.json().catch(() => ({}));
        if (!assistanceResponse.ok) throw new Error(assistancePayload.message || 'Hulpvragen laden mislukt.');
        assistanceRequests.value = assistancePayload.data ?? [];

        if (tables.value.length > 0 && !tables.value.some((table) => table.table_code === selectedTableCode.value)) {
            selectedTableCode.value = tables.value[0].table_code;
        }
    } catch (error) {
        console.error('Load tables error:', error);
        errorMessage.value = error.message;
        toastService.error(errorMessage.value);
    } finally {
        isLoading.value = false;
        isRefreshing.value = false;
    }
};

const resolveAssistanceRequest = async (request) => {
    resolvingAssistanceRequestId.value = request.id;
    try {
        const payload = await postRequest(`/api/admin/table-assistance-requests/${request.id}/resolve`, {
            csrfToken,
            errorMessage: 'Afmelden mislukt.',
        });

        toastService.success(payload.message || `Hulpvraag tafel ${request.table_code} afgemeld.`);
        await loadTables(true); // Explicit silent refresh
    } catch (error) {
        toastService.error(error.message);
    } finally {
        resolvingAssistanceRequestId.value = null;
    }
};

const checkoutSelectedTable = async () => {
    if (!selectedTable.value) return;
    isCheckingOut.value = true;
    try {
        const payload = await postRequest(`/api/admin/table-receipts/${encodeURIComponent(selectedTable.value.table_code)}/checkout`, {
            csrfToken,
            errorMessage: 'PDF maken mislukt.',
        });

        if (payload.receipt_url) window.open(payload.receipt_url, '_blank', 'noopener');
        toastService.success(`Tafel ${selectedTable.value.table_code} afgerekend.`);
        await loadTables(true); // Explicit silent refresh
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isCheckingOut.value = false;
    }
};

const setupRealtime = () => {
    if (!window.Echo) return;

    window.Echo.channel('admin-notifications')
        .listen('.TableAssistanceRequestCreated', (data) => {
            if (!assistanceRequests.value.some((r) => r.id === data.id)) {
                assistanceRequests.value.push(data);
                toastService.info(`Tafel ${data.table_code} vraagt om assistentie!`);
            }
        })
        .listen('.TableAssistanceRequestResolved', (data) => {
            assistanceRequests.value = assistanceRequests.value.filter((r) => r.id !== data.id);
        })
        .listen('.OrderPlaced', (data) => {
            toastService.info(`Nieuwe bestelling voor Tafel ${data.table_code}!`);
            loadTables(true); // Silent refresh
        })
        .listen('.TableCheckoutInitiated', (data) => {
            loadTables(true); // Silent refresh
        });
};

onMounted(() => {
    loadTables();
    setupRealtime();
});
</script>

<template>
    <main class="h-dvh overflow-hidden bg-brand-light text-brand-dark flex font-sans antialiased">
        <AdminSidebar
            :is-open="isSidebarOpen"
            :is-collapsed="isCollapsed"
            active-page="tafels"
            :csrf-token="csrfToken"
            @close="isSidebarOpen = false"
            @toggle-collapse="isCollapsed = !isCollapsed"
        />

        <!-- Workspace -->
        <section class="flex-1 min-w-0 min-h-0 flex flex-col">
            <header class="bg-white border-b border-brand-border px-6 py-4 z-40 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-4">
                    <button @click="isSidebarOpen = true" class="lg:hidden p-2 -ml-2 text-stone-600 hover:bg-stone-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div>
                        <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Tafelbeheer</p>
                        <h1 class="text-xl font-black leading-tight">Actieve Tafels</h1>
                    </div>
                </div>
            </header>

            <div class="flex-1 min-h-0 flex flex-col lg:flex-row overflow-y-auto lg:overflow-hidden custom-scrollbar">
                <!-- Metrics and Content Area -->
                <div class="flex-shrink-0 lg:flex-1 lg:min-h-0 flex flex-col border-r border-brand-border bg-white">
                    <!-- Metrics -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 border-b border-brand-border bg-stone-50/50 flex-shrink-0">
                        <div class="p-6 border-r border-brand-border">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Actieve Tafels</span>
                            <p class="text-2xl font-black text-stone-900 leading-none">{{ tables.length }}</p>
                        </div>
                        <div class="p-6 border-r border-brand-border">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Open Items</span>
                            <p class="text-2xl font-black text-stone-900 leading-none">{{ totalOpenItems }}</p>
                        </div>
                        <div class="p-6 border-r border-brand-border">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Hulpvragen</span>
                            <p class="text-2xl font-black" :class="openAssistanceCount > 0 ? 'text-brand-red' : 'text-stone-900'">{{ openAssistanceCount }}</p>
                        </div>
                        <div class="p-6">
                            <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">Open Bedrag</span>
                            <p class="text-2xl font-black text-brand-red leading-none">{{ formatter.format(totalOpenAmount) }}</p>
                        </div>
                    </div>

                    <!-- Assistance Requests -->
                    <div v-if="assistanceRequests.length > 0" class="flex-shrink-0 border-b border-brand-gold/20 bg-brand-gold-light">
                        <div class="px-6 py-3 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="font-black text-[10px] uppercase tracking-widest text-brand-gold">Hulpvragen</h2>
                                <p class="text-[9px] font-bold text-stone-600">Ober gevraagd aan {{ assistanceRequests.length }} tafel(s)</p>
                            </div>
                        </div>
                        <div class="px-6 pb-4 flex flex-wrap gap-3">
                            <article
                                v-for="request in assistanceRequests"
                                :key="request.id"
                                class="bg-white border border-brand-gold/30 rounded-xl p-2.5 flex items-center gap-3 shadow-sm"
                            >
                                <div class="w-8 h-8 bg-brand-dark rounded-lg flex items-center justify-center text-white flex-shrink-0">
                                    <span class="text-[11px] font-black">{{ request.table_code }}</span>
                                </div>
                                <button
                                    @click="resolveAssistanceRequest(request)"
                                    :disabled="resolvingAssistanceRequestId === request.id"
                                    class="h-8 px-3 bg-brand-gold text-white rounded-lg font-black uppercase tracking-widest text-[8px] disabled:opacity-50"
                                >
                                    {{ resolvingAssistanceRequestId === request.id ? '...' : 'OK' }}
                                </button>
                            </article>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                        <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Openstaande Rekeningen</h3>
                    </div>

                    <div v-if="isLoading" class="p-12 text-center flex-shrink-0 lg:flex-1 lg:flex lg:items-center lg:justify-center">
                        <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                    </div>
                    <div v-else-if="tables.length === 0" class="p-12 text-center flex-shrink-0 lg:flex-1 lg:flex lg:flex-col lg:items-center lg:justify-center">
                        <p class="font-black text-stone-600 uppercase text-[10px] tracking-widest">Geen openstaande bestellingen</p>
                    </div>
                    <div v-else class="divide-y divide-brand-border flex-shrink-0 lg:flex-1 lg:overflow-y-auto custom-scrollbar">
                        <button
                            v-for="table in tables"
                            :key="table.table_code"
                            @click="selectedTableCode = table.table_code"
                            class="w-full flex items-center gap-4 p-4 text-left transition-all hover:bg-stone-50"
                            :class="selectedTable?.table_code === table.table_code ? 'bg-brand-gold-light ring-1 ring-inset ring-brand-gold/30' : ''"
                        >
                            <div class="w-11 h-11 bg-brand-dark rounded-xl flex flex-col items-center justify-center text-white shadow-md">
                                <span class="text-[7px] uppercase font-bold text-stone-300 mb-px">Tafel</span>
                                <span class="text-sm font-black leading-none">{{ table.table_code }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="px-1.5 py-0.5 bg-stone-100 text-stone-700 rounded text-[8px] font-black uppercase tracking-tighter">{{ table.orders_count }} RONDEN</span>
                                    <span class="px-1.5 py-0.5 bg-stone-100 text-stone-700 rounded text-[8px] font-black uppercase tracking-tighter">{{ table.items_count }} ITEMS</span>
                                </div>
                                <p class="text-[9px] font-bold text-stone-600 uppercase tracking-widest">Eerste ronde: {{ formatDate(table.first_order_at).split(' ')[1] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-black text-lg text-brand-red leading-none mb-1">{{ formatter.format(table.total) }}</p>
                                <p class="text-[8px] font-bold text-stone-600 uppercase tracking-widest">Openstaand</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Receipt Preview -->
                <aside class="w-full lg:w-96 flex-shrink-0 flex flex-col bg-stone-50/30">
                    <header class="p-6 border-b border-brand-border flex items-center justify-between bg-white flex-shrink-0">
                        <div>
                            <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Afrekenen</p>
                            <h2 class="text-lg font-black leading-tight">Rekening</h2>
                        </div>
                        <div v-if="selectedTable" class="px-3 py-1.5 bg-stone-50 rounded-lg border border-stone-200 text-center">
                            <p class="text-[7px] uppercase font-bold text-stone-600">Tafel</p>
                            <p class="text-base font-black leading-none text-stone-900">{{ selectedTable.table_code }}</p>
                        </div>
                    </header>

                    <div v-if="selectedTable" class="p-6 border-b border-brand-border bg-stone-50/50 flex gap-3 flex-shrink-0">
                        <div class="flex-1 p-2.5 bg-white rounded-lg border border-brand-border shadow-sm">
                            <p class="text-[7px] uppercase font-bold text-stone-600">Inhoud</p>
                            <p class="text-xs font-black text-stone-900">{{ selectedTable.items_count }} items</p>
                        </div>
                        <div class="flex-1 p-2.5 bg-white rounded-lg border border-brand-border shadow-sm text-right">
                            <p class="text-[7px] uppercase font-bold text-stone-600">Tijdstip</p>
                            <p class="text-xs font-black text-stone-900">{{ formatDate(selectedTable.last_order_at).split(' ')[1] }}</p>
                        </div>
                    </div>

                    <div v-if="!selectedTable" class="flex-1 p-12 text-center space-y-3 flex-shrink-0 lg:flex-1">
                        <p class="text-stone-600 font-bold text-xs uppercase tracking-widest italic">Selecteer een tafel</p>
                    </div>
                    <template v-else>
                        <div class="flex-shrink-0 lg:flex-1 lg:overflow-y-auto p-6 space-y-5 custom-scrollbar">
                            <div v-for="line in selectedTable.lines" :key="`${line.menu_item_id}-${line.unit_price}-${line.notes?.join('|')}`" class="flex items-start gap-3">
                                <div class="w-8 h-8 bg-stone-100 rounded-lg flex items-center justify-center font-black text-[9px] text-stone-600 border border-stone-200 flex-shrink-0">
                                    {{ line.display_number || 'GD' }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start gap-2">
                                        <h4 class="font-black text-stone-900 text-xs leading-tight truncate">{{ line.name }}</h4>
                                        <span class="font-black text-stone-900 text-xs">{{ formatter.format(line.line_total) }}</span>
                                    </div>
                                    <p class="text-[9px] font-bold text-stone-600 mt-0.5">{{ line.quantity }}x {{ formatter.format(line.unit_price) }}</p>
                                    <p v-if="line.notes?.length" class="text-[9px] font-black text-brand-gold mt-1 leading-snug">
                                        {{ line.notes.join(' · ') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 bg-brand-light border-t border-brand-border space-y-5 flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <span class="font-black text-stone-700 uppercase text-[9px] tracking-widest">Eindbedrag</span>
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
                </aside>
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
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--color-brand-gold);
}
</style>
