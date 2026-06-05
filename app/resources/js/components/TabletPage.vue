<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import CocktailPage from './CocktailPage.vue';
import { useOrderLines } from '../composables/useOrderLines';
import { currencyFormatter as formatter } from '../services/formatters';
import { fetchPublicMenuItems } from '../services/menuApi';
import { toastService } from '../services/toastService';

const brandLogo = '/images/brand/de-gouden-draak-emblem.png';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const params = new URLSearchParams(window.location.search);
const pathTableNumber = window.location.pathname.match(/^\/tablet\/(\d+)$/)?.[1] ?? '';
const rawTableNumber = pathTableNumber || params.get('tafel') || params.get('table') || '';

const tableNumber = computed(() => {
    const value = rawTableNumber.trim();
    if (!/^[1-9]\d*$/.test(value)) return null;
    const parsed = Number(value);
    return parsed <= 999 ? parsed : null;
});

const items = ref([]);
const orderHistory = ref([]);
const commonNoteSuggestions = ref([]);
const tableStatus = ref(null);
const cooldownRemainingSeconds = ref(0);
const isLoading = ref(true);
const isHistoryLoading = ref(false);
const isSubmitting = ref(false);
const isRequestingAssistance = ref(false);
const isRefreshingStatus = ref(false);
const activeTabletPanel = ref('menu');
const activeMenuCategory = ref('');
const menuSearchQuery = ref('');
const repeatedSourceOrderId = ref(null);
const errorMessage = ref('');
const orderMessage = ref('');
let cooldownIntervalId = null;

const {
    orderLines,
    customNoteInputs,
    lineCount,
    orderTotal,
    itemQuantityById,
    addItem,
    setOrderLinesFromHistory,
    clearOrderLines,
    increaseQuantity,
    decreaseQuantity,
    addNoteToLine,
    removeNoteFromLine,
} = useOrderLines();

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

const menuCategories = computed(() => groupedItems.value.map((group) => group.category));

const filteredGroupedItems = computed(() => {
    const query = menuSearchQuery.value.trim().toLowerCase();
    return groupedItems.value
        .filter((group) => !activeMenuCategory.value || group.category === activeMenuCategory.value)
        .map((group) => ({
            category: group.category,
            items: group.items.filter((item) => {
                if (!query) return true;
                const searchable = [item.display_number, item.name, item.description, item.category]
                    .filter(Boolean).join(' ').toLowerCase();
                return searchable.includes(query);
            }),
        }))
        .filter((group) => group.items.length > 0);
});

const filteredItemCount = computed(() => filteredGroupedItems.value.reduce((sum, group) => sum + group.items.length, 0));

const hasRoundsAvailable = computed(() => {
    if (!tableStatus.value) return true;
    return tableStatus.value.rounds_used < tableStatus.value.max_rounds;
});

const canOrder = computed(() => hasRoundsAvailable.value && cooldownRemainingSeconds.value === 0);
const activeAssistanceRequest = computed(() => tableStatus.value?.assistance_request ?? null);
const cooldownDisplay = computed(() => {
    const minutes = Math.floor(cooldownRemainingSeconds.value / 60);
    const seconds = cooldownRemainingSeconds.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

const formatOrderDate = (value) => {
    if (!value) return 'Onbekend';
    return new Intl.DateTimeFormat('nl-NL', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const setTableStatus = (status) => {
    tableStatus.value = status;
    cooldownRemainingSeconds.value = status?.cooldown_seconds ?? 0;
};

const loadTableStatus = async () => {
    if (!tableNumber.value) return;
    const response = await fetch(`/api/tablet/tables/${tableNumber.value}/status`, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    if (!response.ok) throw new Error('Status kon niet worden geladen.');
    const payload = await response.json();
    setTableStatus(payload.data ?? null);
};

const refreshStatusAfterCooldown = async () => {
    if (isRefreshingStatus.value || !tableNumber.value) return;
    isRefreshingStatus.value = true;
    try { await loadTableStatus(); } catch (e) { console.error(e); } finally { isRefreshingStatus.value = false; }
};

const startCooldownTimer = () => {
    if (cooldownIntervalId !== null) return;
    cooldownIntervalId = window.setInterval(() => {
        if (cooldownRemainingSeconds.value <= 0) return;
        cooldownRemainingSeconds.value -= 1;
        if (cooldownRemainingSeconds.value === 0) refreshStatusAfterCooldown();
    }, 1000);
};

const loadOrderHistory = async () => {
    if (!tableNumber.value) return;
    isHistoryLoading.value = true;
    try {
        const response = await fetch(`/api/tablet/tables/${tableNumber.value}/history`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!response.ok) throw new Error('Geschiedenis kon niet worden geladen.');
        const payload = await response.json();
        orderHistory.value = payload.data ?? [];
    } finally { isHistoryLoading.value = false; }
};

const loadNoteSuggestions = async () => {
    const response = await fetch('/api/order-line-note-suggestions', {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    if (!response.ok) return;
    const payload = await response.json();
    commonNoteSuggestions.value = payload.data ?? [];
};

const loadTabletData = async () => {
    if (!tableNumber.value) {
        isLoading.value = false;
        errorMessage.value = 'Geef een geldig tafelnummer mee.';
        return;
    }
    isLoading.value = true;
    try {
        await Promise.all([loadTableStatus(), loadOrderHistory(), loadNoteSuggestions()]);
        items.value = await fetchPublicMenuItems({ csrfToken });
    } catch (error) {
        errorMessage.value = error.message;
        toastService.error(errorMessage.value);
    } finally { isLoading.value = false; }
};

const repeatOrder = (order) => {
    setOrderLinesFromHistory(order.lines);
    repeatedSourceOrderId.value = order.id;
};

const submitOrder = async () => {
    if (!canOrder.value) return;
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
                    notes: line.notes,
                })),
            }),
        });
        const payload = await response.json();
        if (!response.ok) {
            if (payload.status) setTableStatus(payload.status);
            throw new Error(payload.message || 'Fout bij bestellen.');
        }
        toastService.success(`Bestelling #${payload.order.id} geplaatst.`);
        clearOrderLines();
        repeatedSourceOrderId.value = null;
        await Promise.all([loadTableStatus(), loadOrderHistory()]);
    } catch (error) {
        toastService.error(error.message);
    } finally { isSubmitting.value = false; }
};

const requestAssistance = async () => {
    if (!tableNumber.value || activeAssistanceRequest.value || isRequestingAssistance.value) return;
    isRequestingAssistance.value = true;
    try {
        const response = await fetch(`/api/tablet/tables/${tableNumber.value}/assistance-requests`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Hulpvraag kon niet worden verstuurd.');

        toastService.success(payload.message || 'Een ober komt zo naar uw tafel.');
        await loadTableStatus();
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isRequestingAssistance.value = false;
    }
};

onMounted(() => {
    startCooldownTimer();
    loadTabletData();
});

onUnmounted(() => {
    if (cooldownIntervalId) window.clearInterval(cooldownIntervalId);
});
</script>

<template>
    <main class="min-h-screen bg-brand-light text-brand-dark font-sans antialiased">
        <!-- Compact Header -->
        <header class="bg-white border-b border-brand-border px-5 py-3 sticky top-0 z-50 shadow-sm">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="brand-lockup">
                    <img
                        class="brand-lockup-mark"
                        :src="brandLogo"
                        alt="De Gouden Draak logo"
                    >
                    <div>
                        <p class="brand-lockup-wordmark text-brand-gold">De Gouden Draak</p>
                        <h1 class="text-xl font-black leading-none">
                            {{ tableNumber ? `Tafel ${tableNumber}` : 'Geen tafel' }}
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-5">
                    <button
                        @click="requestAssistance"
                        :disabled="!tableNumber || !!activeAssistanceRequest || isRequestingAssistance"
                        class="hidden sm:flex h-10 px-4 items-center justify-center gap-2 bg-brand-dark text-white rounded-xl font-black uppercase tracking-widest text-[9px] shadow-sm transition-all active:scale-[0.98] disabled:opacity-45"
                    >
                        <span v-if="isRequestingAssistance" class="w-3.5 h-3.5 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                        {{ activeAssistanceRequest ? 'Ober onderweg' : 'Ober roepen' }}
                    </button>
                    <div v-if="tableStatus" class="flex gap-3">
                        <div class="text-right hidden sm:block">
                            <p class="text-[9px] uppercase font-bold text-stone-600">Ronde</p>
                            <p class="font-black text-stone-900 leading-none">
                                {{ tableStatus.rounds_used }} <span class="text-stone-600">/</span> {{ tableStatus.max_rounds }}
                            </p>
                        </div>
                        <div v-if="!canOrder" class="px-2 py-0.5 bg-[#FFF7ED] border border-[#FED7AA] rounded-md flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-brand-gold animate-pulse"></div>
                            <span class="text-[10px] font-black text-brand-gold uppercase">{{ cooldownDisplay }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] uppercase font-bold text-stone-600">Totaal</p>
                        <p class="font-black text-xl text-brand-red leading-none">{{ formatter.format(orderTotal) }}</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto p-4 md:p-5 grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Left Side: Menu -->
            <section class="lg:col-span-8 space-y-5">
                <!-- Navigation Tabs -->
                <div class="flex p-1 bg-stone-200 rounded-xl max-w-xs">
                    <button
                        v-for="panel in ['menu', 'cocktails']"
                        :key="panel"
                        @click="activeTabletPanel = panel"
                        class="flex-1 py-2 px-4 rounded-lg font-black text-[10px] uppercase tracking-wider transition-all"
                        :class="activeTabletPanel === panel ? 'bg-brand-dark text-white shadow-sm' : 'text-stone-700 hover:text-stone-900'"
                    >
                        {{ panel === 'menu' ? 'Gerechten' : 'Cocktails' }}
                    </button>
                </div>

                <template v-if="activeTabletPanel === 'menu'">
                    <!-- Search & Filters -->
                    <div class="bg-white border border-brand-border rounded-2xl p-3 shadow-sm flex flex-col md:flex-row gap-3">
                        <div class="relative flex-1">
                            <input
                                v-model="menuSearchQuery"
                                type="text"
                                placeholder="Zoek nummer of naam..."
                                class="w-full h-10 bg-stone-50 border-none rounded-xl px-4 pl-10 font-bold text-stone-800 text-sm focus:ring-1 focus:ring-brand-gold outline-none"
                            >
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            </span>
                        </div>

                        <div class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-hide md:max-w-sm">
                            <button
                                v-for="cat in ['', ...menuCategories]"
                                :key="cat"
                                @click="activeMenuCategory = cat"
                                class="px-4 py-1.5 rounded-full whitespace-nowrap font-bold text-[10px] uppercase tracking-wide transition-all"
                                :class="activeMenuCategory === cat ? 'bg-brand-gold text-white' : 'bg-stone-50 text-stone-700 hover:bg-stone-100'"
                            >
                                {{ cat === '' ? 'Alles' : cat }}
                            </button>
                        </div>
                    </div>

                    <!-- Items Grid -->
                    <div v-if="isLoading" class="py-20 text-center">
                        <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                    </div>
                    <div v-else class="space-y-8">
                        <section v-for="group in filteredGroupedItems" :key="group.category" class="space-y-3">
                            <h3 class="flex items-center gap-3">
                                <span class="text-stone-900 font-black uppercase tracking-widest text-[10px]">{{ group.category }}</span>
                                <div class="flex-1 h-px bg-stone-200"></div>
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <button
                                    v-for="item in group.items"
                                    :key="item.id"
                                    @click="addItem(item)"
                                    class="group flex items-center gap-3 p-3 bg-white border border-brand-border rounded-xl text-left transition-all hover:border-brand-gold hover:shadow-md"
                                    :class="itemQuantityById.has(item.id) ? 'ring-1 ring-brand-gold border-transparent bg-stone-50' : ''"
                                >
                                    <div
                                        class="w-10 h-10 flex-shrink-0 rounded-lg flex items-center justify-center font-black text-xs transition-colors"
                                        :class="itemQuantityById.has(item.id) ? 'bg-brand-gold text-white' : 'bg-stone-50 text-stone-600 group-hover:bg-brand-dark group-hover:text-white'"
                                    >
                                        {{ item.display_number || '-' }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-center gap-2">
                                            <h4 class="font-black text-stone-900 text-xs truncate">{{ item.name }}</h4>
                                            <span class="font-black text-brand-red text-xs">{{ formatter.format(item.price) }}</span>
                                        </div>
                                        <p class="text-[10px] text-stone-600 font-medium truncate mt-0.5">
                                            {{ item.description || item.category }}
                                        </p>
                                    </div>
                                    <div v-if="itemQuantityById.has(item.id)" class="bg-brand-dark text-white w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-black">
                                        {{ itemQuantityById.get(item.id) }}
                                    </div>
                                </button>
                            </div>
                        </section>
                    </div>
                </template>

                <CocktailPage v-else />
            </section>

            <!-- Right Side: Order Summary -->
            <aside class="lg:col-span-4 space-y-5 sticky top-[80px] self-start max-h-[calc(100dvh-100px)] overflow-y-auto custom-scrollbar pr-1">
                <!-- Assistance -->
                <div class="bg-white border border-brand-border rounded-2xl p-5 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-black text-xs uppercase tracking-widest text-stone-700">Hulp nodig?</h2>
                            <p class="text-[10px] font-bold text-stone-600 mt-1">
                                {{ activeAssistanceRequest ? 'Een ober is gewaarschuwd.' : 'Roep een ober naar uw tafel.' }}
                            </p>
                        </div>
                        <span
                            class="w-9 h-9 rounded-xl flex items-center justify-center border"
                            :class="activeAssistanceRequest ? 'bg-[#FFF7ED] border-[#FED7AA] text-brand-gold' : 'bg-stone-50 border-stone-100 text-stone-700'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </span>
                    </div>
                    <button
                        @click="requestAssistance"
                        :disabled="!tableNumber || !!activeAssistanceRequest || isRequestingAssistance"
                        class="w-full h-11 bg-brand-dark text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-md transition-all active:scale-[0.98] disabled:opacity-45 flex items-center justify-center gap-2"
                    >
                        <span v-if="isRequestingAssistance" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                        {{ activeAssistanceRequest ? 'Ober is onderweg' : 'Ober roepen' }}
                    </button>
                </div>

                <!-- Current Order -->
                <div class="bg-white border border-brand-border rounded-2xl shadow-lg flex flex-col h-fit">
                    <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
                        <div>
                            <h2 class="font-black text-base">Bestelling</h2>
                            <p class="text-[9px] uppercase font-bold text-stone-600">{{ lineCount }} items</p>
                        </div>
                        <button
                            v-if="orderLines.length > 0"
                            @click="clearOrderLines"
                            class="text-[9px] uppercase font-black text-stone-700 hover:text-red-600 transition-colors"
                        >
                            Wissen
                        </button>
                    </div>

                    <div class="p-5 space-y-3">
                        <p v-if="orderLines.length === 0" class="text-stone-600 font-bold text-[10px] text-center italic py-4">Kies gerechten</p>
                        <article v-for="line in orderLines" :key="line.id" class="space-y-2 border-b border-stone-100 pb-3 last:border-b-0 last:pb-0">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="font-black text-stone-900 text-[11px] truncate">{{ line.display_number }} {{ line.name }}</p>
                                    <p class="text-[9px] font-bold text-stone-600">{{ formatter.format(line.price) }} / st.</p>
                                </div>
                                <div class="flex items-center bg-stone-50 rounded-lg p-0.5 border border-stone-100">
                                    <button @click="decreaseQuantity(line)" class="w-6 h-6 flex items-center justify-center font-black text-stone-700 hover:text-stone-900">-</button>
                                    <span class="w-6 text-center font-black text-[10px]">{{ line.quantity }}</span>
                                    <button @click="increaseQuantity(line)" class="w-6 h-6 flex items-center justify-center font-black text-stone-700 hover:text-stone-900">+</button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div v-if="line.notes.length > 0" class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="note in line.notes"
                                        :key="note"
                                        @click="removeNoteFromLine(line, note)"
                                        class="px-2 py-1 bg-[#FFF7ED] border border-[#FED7AA] rounded-md text-[9px] font-black text-brand-gold"
                                    >
                                        {{ note }} ×
                                    </button>
                                </div>
                                <div v-if="commonNoteSuggestions.length > 0" class="flex gap-1.5 overflow-x-auto scrollbar-hide">
                                    <button
                                        v-for="suggestion in commonNoteSuggestions"
                                        :key="suggestion.note"
                                        @click="addNoteToLine(line, suggestion.note)"
                                        class="px-2 py-1 bg-stone-50 hover:bg-stone-100 border border-stone-100 rounded-md whitespace-nowrap text-[9px] font-bold text-stone-700"
                                    >
                                        {{ suggestion.note }}
                                    </button>
                                </div>
                                <form class="flex gap-2" @submit.prevent="addNoteToLine(line, customNoteInputs[line.id] ?? '')">
                                    <input
                                        v-model="customNoteInputs[line.id]"
                                        type="text"
                                        maxlength="160"
                                        placeholder="Opmerking..."
                                        class="min-w-0 flex-1 h-9 bg-stone-50 border border-stone-100 rounded-lg px-3 text-[10px] font-bold outline-none focus:ring-1 focus:ring-brand-gold"
                                    >
                                    <button type="submit" class="h-9 px-3 bg-brand-dark text-white rounded-lg text-[9px] font-black uppercase tracking-widest">Toevoegen</button>
                                </form>
                            </div>
                        </article>
                    </div>

                    <div class="p-5 bg-stone-50 rounded-b-2xl border-t border-stone-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-stone-700 uppercase text-[9px] tracking-widest">Totaal</span>
                            <span class="font-black text-xl text-brand-red">{{ formatter.format(orderTotal) }}</span>
                        </div>

                        <button
                            @click="submitOrder"
                            :disabled="!canOrder || orderLines.length === 0 || isSubmitting"
                            class="w-full h-11 bg-brand-gold text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-md transition-all active:scale-[0.98] disabled:opacity-40 disabled:grayscale flex items-center justify-center gap-2"
                        >
                            <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Bezig...' : 'Bestellen' }}
                        </button>
                    </div>
                </div>

                <!-- Previous Rounds -->
                <div class="bg-white border border-brand-border rounded-2xl p-5 shadow-sm space-y-3">
                    <h2 class="font-black text-xs uppercase tracking-widest text-stone-700">Eerdere rondes</h2>
                    <div v-if="orderHistory.length === 0" class="text-center py-4 text-stone-600 font-bold text-[10px] italic border border-dashed border-stone-100 rounded-xl">
                        Geen geschiedenis
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="order in orderHistory" :key="order.id" class="border border-stone-100 rounded-xl overflow-hidden text-[10px]">
                            <div class="bg-stone-50 px-3 py-1.5 flex items-center justify-between border-b border-stone-100">
                                <p class="font-black text-stone-900 uppercase">Ronde #{{ order.id }}</p>
                                <button @click="repeatOrder(order)" class="font-black text-brand-gold uppercase">Herhaal</button>
                            </div>
                            <div class="p-2.5 space-y-0.5">
                                <div v-for="line in order.lines" :key="`${line.menu_item_id}-${line.notes?.join('|')}`" class="text-[10px]">
                                    <div class="flex justify-between items-center">
                                        <span class="text-stone-600"><span class="font-black text-stone-900">{{ line.quantity }}x</span> {{ line.name }}</span>
                                        <span v-if="!line.is_active" class="text-[7px] font-black text-red-500 uppercase bg-red-50 px-1 rounded">OP</span>
                                    </div>
                                    <p v-if="line.notes?.length" class="mt-0.5 text-[9px] font-bold text-brand-gold">
                                        {{ line.notes.join(' · ') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
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

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
