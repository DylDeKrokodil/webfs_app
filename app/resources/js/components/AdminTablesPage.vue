<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { postRequest } from '../services/apiService';
import { useAdminShell } from '../composables/useAdminShell';
import { adminDateTimeFormatter as dateFormatter, currencyFormatter as formatter } from '../services/formatters';
import { toastService } from '../services/toastService';

const { csrfToken, isSidebarOpen } = useAdminShell();

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
        <AdminSidebar :is-open="isSidebarOpen" active-page="tafels" :csrf-token="csrfToken" @close="isSidebarOpen = false" />

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

            <div class="flex-1 min-h-0 p-4 lg:p-6 overflow-y-auto lg:overflow-hidden flex flex-col gap-4">
                <!-- Metrics - More Compact -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 flex-shrink-0">
                    <div v-for="(val, label) in { 'Actieve Tafels': tables.length, 'Open Items': totalOpenItems, 'Hulpvragen': openAssistanceCount, 'Open Bedrag': formatter.format(totalOpenAmount), 'Geselecteerd': selectedTable ? `Tafel ${selectedTable.table_code}` : '-' }" :key="label" class="bg-white border border-brand-border px-4 py-3 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-600 tracking-wider mb-1">{{ label }}</span>
                        <strong class="block text-lg font-black text-stone-900 leading-none">{{ val }}</strong>
                    </div>
                </div>

                <section
                    v-if="assistanceRequests.length > 0"
                    class="bg-white border border-brand-gold/40 rounded-2xl shadow-sm overflow-hidden flex-shrink-0"
                >
                    <div class="px-5 py-3 bg-[#FFF7ED] border-b border-[#FED7AA] flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-black text-sm text-stone-900">Hulpvragen</h2>
                            <p class="text-[10px] font-bold text-stone-600">{{ assistanceRequests.length }} tafel(s) wachten op een ober</p>
                        </div>
                        <span class="w-9 h-9 rounded-xl bg-brand-gold text-white flex items-center justify-center font-black text-sm">
                            {{ assistanceRequests.length }}
                        </span>
                    </div>
                    <div class="p-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        <article
                            v-for="request in assistanceRequests"
                            :key="request.id"
                            class="border border-stone-100 rounded-xl p-3 flex items-center gap-3"
                        >
                            <div class="w-11 h-11 bg-brand-dark rounded-xl flex flex-col items-center justify-center text-white shadow-sm flex-shrink-0">
                                <span class="text-[7px] uppercase font-bold text-stone-300 mb-px">Tafel</span>
                                <span class="text-sm font-black leading-none">{{ request.table_code }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-stone-900 text-xs">Ober gevraagd</p>
                                <p class="text-[9px] font-bold text-stone-600">{{ formatDate(request.created_at) }}</p>
                            </div>
                            <button
                                @click="resolveAssistanceRequest(request)"
                                :disabled="resolvingAssistanceRequestId === request.id"
                                class="h-9 px-3 bg-brand-gold text-white rounded-lg font-black uppercase tracking-widest text-[8px] disabled:opacity-50"
                            >
                                {{ resolvingAssistanceRequestId === request.id ? '...' : 'Afmelden' }}
                            </button>
                        </article>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 items-stretch flex-1 min-h-0">
                    <!-- Tables List -->
                    <section class="lg:col-span-7 min-h-[320px] lg:min-h-0">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col h-full">
                            <div class="px-6 py-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
                                <h3 class="font-black text-base text-stone-900">Wachtende Tafels</h3>
                                <p class="text-[10px] font-bold text-stone-600">Beheer openstaande rekeningen</p>
                            </div>

                            <div v-if="isLoading" class="p-12 text-center flex-1">
                                <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                            </div>
                            <div v-else-if="tables.length === 0" class="p-12 text-center space-y-2 flex-1">
                                <p class="font-black text-stone-600 italic text-sm">Geen openstaande bestellingen</p>
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
                                        <span class="text-[8px] uppercase font-bold text-stone-300 mb-px">Tafel</span>
                                        <span class="text-base font-black leading-none">{{ table.table_code }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="px-1.5 py-0.5 bg-stone-100 text-stone-600 rounded text-[8px] font-black uppercase tracking-tighter">{{ table.orders_count }} RONDEN</span>
                                            <span class="px-1.5 py-0.5 bg-stone-100 text-stone-600 rounded text-[8px] font-black uppercase tracking-tighter">{{ table.items_count }} ITEMS</span>
                                        </div>
                                        <p class="text-[10px] font-bold text-stone-600">Eerste ronde: {{ formatDate(table.first_order_at) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-black text-lg text-brand-red leading-none mb-1">{{ formatter.format(table.total) }}</p>
                                        <p class="text-[8px] font-bold text-stone-600 uppercase tracking-widest">Openstaand</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Receipt Preview -->
                    <aside class="lg:col-span-5 min-h-[420px] lg:min-h-0">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-lg overflow-hidden flex flex-col h-full">
                            <div class="p-6 border-b border-stone-100 bg-brand-dark text-white flex-shrink-0">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-black text-lg leading-none">Rekening</h3>
                                        <p class="text-[9px] uppercase font-bold text-stone-300 mt-1 tracking-widest">Detailoverzicht</p>
                                    </div>
                                    <div v-if="selectedTable" class="px-3 py-1.5 bg-white/10 rounded-lg border border-white/10 text-center">
                                        <p class="text-[7px] uppercase font-bold text-stone-300">Tafel</p>
                                        <p class="text-base font-black leading-none">{{ selectedTable.table_code }}</p>
                                    </div>
                                </div>
                                <div v-if="selectedTable" class="flex gap-3">
                                    <div class="flex-1 p-2.5 bg-white/5 rounded-lg border border-white/5">
                                        <p class="text-[7px] uppercase font-bold text-stone-300">Inhoud</p>
                                        <p class="text-xs font-black">{{ selectedTable.items_count }} items</p>
                                    </div>
                                    <div class="flex-1 p-2.5 bg-white/5 rounded-lg border border-white/5 text-right">
                                        <p class="text-[7px] uppercase font-bold text-stone-300">Tijdstip</p>
                                        <p class="text-xs font-black">{{ formatDate(selectedTable.last_order_at).split(' ')[1] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!selectedTable" class="flex-1 p-12 text-center space-y-3">
                                <p class="text-stone-600 font-bold text-xs italic">Selecteer een tafel</p>
                            </div>
                            <template v-else>
                                <div class="flex-1 overflow-y-auto p-6 space-y-5 custom-scrollbar">
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

                                <div class="p-6 bg-brand-light border-t border-stone-100 space-y-5 flex-shrink-0">
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
