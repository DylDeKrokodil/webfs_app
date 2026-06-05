<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import CocktailPage from './CocktailPage.vue';
import { toastService } from '../services/toastService';

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
const orderLines = ref([]);
const orderHistory = ref([]);
const tableStatus = ref(null);
const cooldownRemainingSeconds = ref(0);
const isLoading = ref(true);
const isHistoryLoading = ref(false);
const isSubmitting = ref(false);
const isRefreshingStatus = ref(false);
const activeTabletPanel = ref('menu');
const activeMenuCategory = ref('');
const menuSearchQuery = ref('');
const repeatedSourceOrderId = ref(null);
const errorMessage = ref('');
const orderMessage = ref('');
let cooldownIntervalId = null;

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

const menuCategories = computed(() => groupedItems.value.map((group) => group.category));
const itemQuantityById = computed(() => new Map(orderLines.value.map((line) => [line.id, line.quantity])));

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
const lineCount = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity, 0));
const orderTotal = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity * Number(line.price), 0));

const hasRoundsAvailable = computed(() => {
    if (!tableStatus.value) return true;
    return tableStatus.value.rounds_used < tableStatus.value.max_rounds;
});

const canOrder = computed(() => hasRoundsAvailable.value && cooldownRemainingSeconds.value === 0);
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

const loadTabletData = async () => {
    if (!tableNumber.value) {
        isLoading.value = false;
        errorMessage.value = 'Geef een geldig tafelnummer mee.';
        return;
    }
    isLoading.value = true;
    try {
        await Promise.all([loadTableStatus(), loadOrderHistory()]);
        const response = await fetch('/api/menu-items', {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!response.ok) throw new Error('Menukaart kon niet worden geladen.');
        const payload = await response.json();
        items.value = payload.data ?? [];
    } catch (error) {
        errorMessage.value = error.message;
        toastService.error(errorMessage.value);
    } finally { isLoading.value = false; }
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
        price: Number(item.price ?? item.current_price),
        quantity: 1,
    });
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

const decreaseQuantity = (line) => {
    if (line.quantity <= 1) {
        orderLines.value = orderLines.value.filter((l) => l.id !== line.id);
        return;
    }
    line.quantity -= 1;
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
                })),
            }),
        });
        const payload = await response.json();
        if (!response.ok) {
            if (payload.status) setTableStatus(payload.status);
            throw new Error(payload.message || 'Fout bij bestellen.');
        }
        toastService.success(`Bestelling #${payload.order.id} geplaatst.`);
        orderLines.value = [];
        repeatedSourceOrderId.value = null;
        await Promise.all([loadTableStatus(), loadOrderHistory()]);
    } catch (error) {
        toastService.error(error.message);
    } finally { isSubmitting.value = false; }
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
    <main class="min-h-screen bg-[#FAFAF9] text-[#1C1917] font-sans antialiased">
        <!-- Premium Header -->
        <header class="bg-white border-b border-[#D6D3D1] px-6 py-4 sticky top-0 z-50 shadow-sm">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-[#7F1D1D] rounded-xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-black text-xl">D</span>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest font-black text-[#A16207]">Gouden Draak Tablet</p>
                        <h1 class="text-2xl font-black leading-none">
                            {{ tableNumber ? `Tafel ${tableNumber}` : 'Geen tafel' }}
                        </h1>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <div v-if="tableStatus" class="flex gap-4">
                        <div class="text-right hidden sm:block">
                            <p class="text-[10px] uppercase font-bold text-stone-500">Ronde</p>
                            <p class="font-black text-lg text-stone-900 leading-none">
                                {{ tableStatus.rounds_used }} <span class="text-stone-400 font-medium">/</span> {{ tableStatus.max_rounds }}
                            </p>
                        </div>
                        <div v-if="!canOrder" class="px-3 py-1 bg-[#FFF7ED] border border-[#FED7AA] rounded-lg flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-[#A16207] animate-pulse"></div>
                            <span class="text-xs font-black text-[#A16207] uppercase">{{ cooldownDisplay }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] uppercase font-bold text-stone-500">Subtotaal</p>
                        <p class="font-black text-2xl text-[#7F1D1D] leading-none">{{ formatter.format(orderTotal) }}</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Side: Menu -->
            <section class="lg:col-span-8 space-y-6">
                <!-- Navigation Tabs -->
                <div class="flex p-1 bg-stone-200 rounded-xl">
                    <button
                        v-for="panel in ['menu', 'cocktails']"
                        :key="panel"
                        @click="activeTabletPanel = panel"
                        class="flex-1 py-3 px-4 rounded-lg font-black text-sm uppercase tracking-wide transition-all duration-200"
                        :class="activeTabletPanel === panel ? 'bg-[#1C1917] text-white shadow-md' : 'text-stone-600 hover:text-stone-900'"
                    >
                        {{ panel === 'menu' ? 'Gerechten' : 'Cocktails' }}
                    </button>
                </div>

                <template v-if="activeTabletPanel === 'menu'">
                    <!-- Search & Filters -->
                    <div class="bg-white border border-[#D6D3D1] rounded-2xl p-4 shadow-sm space-y-4">
                        <div class="relative">
                            <input
                                v-model="menuSearchQuery"
                                type="text"
                                placeholder="Zoek op nummer of naam..."
                                class="w-full h-12 bg-stone-100 border-none rounded-xl px-4 pl-12 font-bold text-stone-800 focus:ring-2 focus:ring-[#A16207] outline-none transition-all"
                            >
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-400">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            </span>
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                            <button
                                @click="activeMenuCategory = ''"
                                class="px-5 py-2 rounded-full whitespace-nowrap font-bold text-sm transition-all"
                                :class="activeMenuCategory === '' ? 'bg-[#A16207] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200'"
                            >
                                Alles
                            </button>
                            <button
                                v-for="cat in menuCategories"
                                :key="cat"
                                @click="activeMenuCategory = cat"
                                class="px-5 py-2 rounded-full whitespace-nowrap font-bold text-sm transition-all"
                                :class="activeMenuCategory === cat ? 'bg-[#A16207] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200'"
                            >
                                {{ cat }}
                            </button>
                        </div>
                    </div>

                    <!-- Items Grid -->
                    <div v-if="isLoading" class="py-20 text-center space-y-4">
                        <div class="w-12 h-12 border-4 border-stone-200 border-t-[#A16207] rounded-full animate-spin mx-auto"></div>
                        <p class="font-black text-stone-400 uppercase tracking-widest text-sm">Gerechten laden...</p>
                    </div>
                    <div v-else-if="filteredGroupedItems.length === 0" class="py-20 text-center space-y-2 text-stone-400">
                        <p class="font-black text-lg">Geen gerechten gevonden</p>
                        <p class="font-medium">Probeer een andere zoekterm of categorie.</p>
                    </div>
                    <div v-else class="space-y-10">
                        <section v-for="group in filteredGroupedItems" :key="group.category" class="space-y-4">
                            <h3 class="flex items-center gap-3">
                                <span class="text-stone-900 font-black uppercase tracking-widest text-sm">{{ group.category }}</span>
                                <div class="flex-1 h-px bg-[#D6D3D1]"></div>
                                <span class="text-stone-400 text-xs font-bold">{{ group.items.length }} items</span>
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <button
                                    v-for="item in group.items"
                                    :key="item.id"
                                    @click="addItem(item)"
                                    class="group flex items-start gap-4 p-4 bg-white border border-[#D6D3D1] rounded-2xl text-left transition-all duration-200 hover:border-[#A16207] hover:shadow-lg active:scale-[0.98]"
                                    :class="itemQuantityById.has(item.id) ? 'ring-2 ring-[#A16207] border-transparent bg-stone-50' : ''"
                                >
                                    <div
                                        class="w-12 h-12 flex-shrink-0 rounded-xl flex items-center justify-center font-black text-sm transition-colors"
                                        :class="itemQuantityById.has(item.id) ? 'bg-[#A16207] text-white' : 'bg-stone-100 text-stone-500 group-hover:bg-[#A16207] group-hover:text-white'"
                                    >
                                        {{ item.display_number || '-' }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start gap-2">
                                            <h4 class="font-black text-stone-900 truncate leading-tight">{{ item.name }}</h4>
                                            <span class="font-black text-[#7F1D1D] whitespace-nowrap">{{ formatter.format(item.price) }}</span>
                                        </div>
                                        <p class="text-xs text-stone-500 font-medium line-clamp-2 mt-1 leading-relaxed">
                                            {{ item.description || item.category }}
                                        </p>
                                    </div>
                                    <div v-if="itemQuantityById.has(item.id)" class="flex-shrink-0">
                                        <div class="bg-[#1C1917] text-white w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-black shadow-md">
                                            {{ itemQuantityById.get(item.id) }}x
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </section>
                    </div>
                </template>

                <CocktailPage v-else />
            </section>

            <!-- Right Side: Order Summary -->
            <aside class="lg:col-span-4 space-y-6 sticky top-[100px] self-start max-h-[calc(100dvh-120px)] overflow-y-auto pr-2 custom-scrollbar">
                <!-- Current Order -->
                <div class="bg-white border border-[#D6D3D1] rounded-3xl shadow-xl flex flex-col h-fit">
                    <div class="p-6 border-b border-stone-100 flex items-center justify-between">
                        <div>
                            <h2 class="font-black text-xl">Bestelling</h2>
                            <p class="text-[10px] uppercase font-bold text-stone-400">{{ lineCount }} items geselecteerd</p>
                        </div>
                        <button
                            v-if="orderLines.length > 0"
                            @click="orderLines = []"
                            class="text-[10px] uppercase font-black text-stone-400 hover:text-red-600 transition-colors"
                        >
                            Leegmaken
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto max-h-[40vh] p-6 space-y-4">
                        <div v-if="orderLines.length === 0" class="py-10 text-center space-y-3">
                            <div class="w-16 h-16 bg-stone-50 rounded-full flex items-center justify-center mx-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D6D3D1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                            </div>
                            <p class="text-stone-400 font-bold text-sm italic">Kies gerechten om te beginnen</p>
                        </div>

                        <article v-for="line in orderLines" :key="line.id" class="flex items-center gap-4 group">
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-stone-900 text-sm truncate leading-tight">{{ line.display_number }} {{ line.name }}</p>
                                <p class="text-[10px] font-bold text-stone-400">{{ formatter.format(line.price) }} / stuk</p>
                            </div>
                            <div class="flex items-center bg-stone-100 rounded-lg p-1">
                                <button @click="decreaseQuantity(line)" class="w-8 h-8 flex items-center justify-center font-black text-stone-500 hover:bg-white hover:text-stone-900 rounded-md transition-all">-</button>
                                <span class="w-8 text-center font-black text-sm">{{ line.quantity }}</span>
                                <button @click="line.quantity++" class="w-8 h-8 flex items-center justify-center font-black text-stone-500 hover:bg-white hover:text-stone-900 rounded-md transition-all">+</button>
                            </div>
                        </article>
                    </div>

                    <div class="p-6 bg-stone-50 rounded-b-3xl border-t border-stone-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-stone-500 uppercase text-[10px] tracking-widest">Totaal bedrag</span>
                            <span class="font-black text-2xl text-[#7F1D1D]">{{ formatter.format(orderTotal) }}</span>
                        </div>

                        <button
                            @click="submitOrder"
                            :disabled="!canOrder || orderLines.length === 0 || isSubmitting"
                            class="w-full h-14 bg-[#1C1917] text-white rounded-2xl font-black uppercase tracking-widest shadow-lg shadow-stone-200 transition-all active:scale-[0.98] disabled:opacity-50 disabled:grayscale disabled:scale-100 flex items-center justify-center gap-3"
                        >
                            <span v-if="isSubmitting" class="w-5 h-5 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            {{ isSubmitting ? 'Versturen...' : 'Bestelling plaatsen' }}
                        </button>

                        <p v-if="!canOrder && tableStatus" class="text-center text-[10px] font-bold text-[#A16207] uppercase bg-[#FFF7ED] py-2 rounded-lg border border-[#FED7AA]">
                            {{ tableStatus.message }}
                        </p>
                    </div>
                </div>

                <!-- Previous Rounds -->
                <div class="bg-white border border-[#D6D3D1] rounded-3xl p-6 shadow-sm space-y-4">
                    <h2 class="font-black text-lg">Eerdere rondes</h2>

                    <div v-if="isHistoryLoading" class="py-10 text-center">
                        <div class="w-8 h-8 border-3 border-stone-100 border-t-[#A16207] rounded-full animate-spin mx-auto"></div>
                    </div>
                    <p v-else-if="orderHistory.length === 0" class="text-center py-4 text-stone-400 font-bold text-xs italic border-2 border-dashed border-stone-100 rounded-xl">
                        Nog geen eerdere rondes
                    </p>
                    <div v-else class="space-y-4">
                        <div v-for="order in orderHistory" :key="order.id" class="border border-stone-100 rounded-xl overflow-hidden">
                            <div class="bg-stone-50 px-4 py-2 flex items-center justify-between border-b border-stone-100">
                                <div>
                                    <p class="font-black text-[10px] text-stone-900 uppercase">Ronde #{{ order.id }}</p>
                                    <p class="text-[9px] font-bold text-stone-400">{{ formatOrderDate(order.created_at) }}</p>
                                </div>
                                <button @click="repeatOrder(order)" class="text-[10px] font-black text-[#A16207] hover:underline uppercase">Herhaal</button>
                            </div>
                            <div class="p-3 space-y-1">
                                <div v-for="line in order.lines" :key="line.menu_item_id" class="flex justify-between items-center text-[11px]">
                                    <span class="text-stone-600 font-medium">
                                        <span class="font-black text-stone-900">{{ line.quantity }}x</span> {{ line.name }}
                                    </span>
                                    <span v-if="!line.is_active" class="text-[9px] font-black text-red-500 uppercase bg-red-50 px-1.5 py-0.5 rounded">Niet meer beschikbaar</span>
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
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #D6D3D1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #A16207;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
