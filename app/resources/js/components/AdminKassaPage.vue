<script setup>
import { onMounted, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { useAdminShell } from '../composables/useAdminShell';
import { useMenuItems } from '../composables/useMenuItems';
import { useMenuSync } from '../composables/useMenuSync';
import { useOrderLines } from '../composables/useOrderLines';
import { currencyFormatter as formatter } from '../services/formatters';
import { toastService } from '../services/toastService';

const { csrfToken, isSidebarOpen, isCollapsed } = useAdminShell();

const categories = ref([]);
const items = ref([]);
const query = ref('');
const categoryFilter = ref('all');
const commonNoteSuggestions = ref([]);
const isLoading = ref(true);
const isCheckingOut = ref(false);
const errorMessage = ref('');

const {
    orderLines,
    customNoteInputs,
    lineCount,
    orderTotal,
    addItem,
    clearOrderLines,
    increaseQuantity,
    decreaseQuantity,
    addNoteToLine,
    removeNoteFromLine,
} = useOrderLines();

const { handleMenuItemUpdated } = useMenuSync(items);

const { visibleItems, groupedItems } = useMenuItems({
    items,
    query,
    categoryFilter,
    activeOnly: true,
});

const loadMenu = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/admin/menu-items', {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!response.ok) throw new Error('Laden mislukt.');
        const payload = await response.json();
        categories.value = (payload.categories ?? []).filter((cat) => cat.is_active);
        items.value = payload.items ?? [];
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const loadNoteSuggestions = async () => {
    try {
        const response = await fetch('/api/order-line-note-suggestions', {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        if (!response.ok) throw new Error('Suggesties laden mislukt.');
        const payload = await response.json();
        commonNoteSuggestions.value = payload.data ?? [];
    } catch (error) {
        console.error(error);
    }
};

const checkoutOrder = async () => {
    if (orderLines.value.length === 0) return;
    isCheckingOut.value = true;
    try {
        const response = await fetch('/api/admin/orders', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                lines: orderLines.value.map((l) => ({
                    menu_item_id: l.id,
                    quantity: l.quantity,
                    notes: l.notes,
                })),
            }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Fout bij afrekenen.');
        toastService.success(`Bestelling #${payload.order.id} afgerekend.`);
        clearOrderLines();
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isCheckingOut.value = false;
    }
};

const setupRealtime = () => {
    if (!window.Echo) return;

    window.Echo.channel('menu-updates')
        .listen('.MenuItemUpdated', handleMenuItemUpdated);

    window.Echo.channel('admin-notifications')
        .listen('.CheckoutCompleted', (data) => {
            console.log('Real-time: Checkout Completed', data);
            // Kassa usually doesn't show history, but we could add a toast or similar
        });
};

onMounted(() => {
    loadMenu();
    loadNoteSuggestions();
    setupRealtime();
});
</script>

<template>
    <main class="h-dvh overflow-hidden bg-brand-light text-brand-dark flex font-sans antialiased">
        <AdminSidebar
            :is-open="isSidebarOpen"
            :is-collapsed="isCollapsed"
            active-page="kassa"
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
                        <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Verkoop</p>
                        <h1 class="text-xl font-black leading-tight">Kassa</h1>
                    </div>
                </div>
            </header>

            <div class="flex-1 min-h-0 flex flex-col lg:flex-row overflow-y-auto lg:overflow-hidden custom-scrollbar">
                <!-- Kassa List -->
                <section class="flex-shrink-0 lg:flex-1 lg:min-h-0 flex flex-col border-r border-brand-border bg-white">
                    <div class="p-6 border-b border-brand-border space-y-4 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-[10px] uppercase tracking-widest text-stone-600">Snel Zoeken</h3>
                            <span class="text-[9px] font-black uppercase tracking-tighter text-brand-gold">{{ visibleItems.length }} GERECHTEN</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="relative">
                                <input v-model="query" type="search" placeholder="Naam of nummer..." autofocus class="w-full h-10 bg-stone-50 border border-stone-200 rounded-lg px-3 pl-9 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                                </span>
                            </div>
                            <select v-model="categoryFilter" class="h-10 bg-stone-50 border border-stone-200 rounded-lg px-2 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                <option value="all">Alle Categorieën</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="isLoading" class="p-12 text-center flex-shrink-0 lg:flex-1 lg:flex lg:items-center lg:justify-center">
                        <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                    </div>
                    <div v-else class="divide-y divide-brand-border flex-shrink-0 lg:flex-1 lg:overflow-y-auto custom-scrollbar">
                        <div v-for="group in groupedItems" :key="group.category">
                            <div class="px-6 py-2 bg-stone-50 text-[9px] font-black uppercase text-stone-700 tracking-widest">{{ group.category }}</div>
                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                @click="addItem(item)"
                                class="w-full flex items-center gap-4 px-6 py-3 text-left transition-all hover:bg-stone-50 group border-b border-stone-50 last:border-0"
                            >
                                <span class="w-8 font-black text-xs text-brand-red text-center">{{ item.display_number || '-' }}</span>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-black text-stone-900 text-xs truncate leading-tight">{{ item.name }}</h4>
                                    <p class="text-[9px] font-bold text-stone-600 uppercase tracking-tighter mt-0.5">{{ item.category }}</p>
                                </div>
                                <div class="text-right flex items-center gap-4">
                                    <span class="font-black text-stone-900 text-xs">{{ formatter.format(item.price) }}</span>
                                    <span class="px-2 py-1 bg-brand-dark text-white text-[8px] font-black uppercase rounded-md opacity-0 group-hover:opacity-100 transition-all transform translate-x-2 group-hover:translate-x-0">Toevoegen</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Cart -->
                <aside class="w-full lg:w-96 flex-shrink-0 flex flex-col bg-stone-50/30">
                    <header class="p-6 border-b border-brand-border flex items-center justify-between bg-white flex-shrink-0">
                        <div>
                            <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Bestelling</p>
                            <h2 class="text-lg font-black leading-tight">Winkelmand</h2>
                        </div>
                        <button v-if="orderLines.length > 0" @click="clearOrderLines" class="text-[9px] font-black text-stone-600 hover:text-red-500 uppercase tracking-widest transition-colors">Leegmaken</button>
                    </header>

                    <div class="flex-shrink-0 lg:flex-1 lg:overflow-y-auto p-6 space-y-4 custom-scrollbar">
                        <div v-if="orderLines.length === 0" class="h-full flex flex-col items-center justify-center text-center py-10">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-stone-400 mb-4"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                            <p class="text-stone-600 font-bold text-[10px] uppercase tracking-widest">Mandje is leeg</p>
                        </div>
                        <article v-for="line in orderLines" :key="line.id" class="space-y-3 border-b border-stone-100 pb-4 last:border-b-0 last:pb-0">
                            <div class="flex items-center gap-3 group">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-black text-stone-900 text-xs leading-tight truncate">{{ line.display_number }} {{ line.name }}</h4>
                                    <p class="text-[9px] font-bold text-stone-600 mt-0.5">{{ formatter.format(line.price) }} / st.</p>
                                </div>
                                <div class="flex items-center bg-white rounded-lg p-0.5 border border-stone-200 shadow-sm">
                                    <button @click="decreaseQuantity(line)" class="w-7 h-7 flex items-center justify-center font-black text-stone-700 hover:bg-stone-50 rounded-md">-</button>
                                    <span class="w-7 text-center font-black text-[10px]">{{ line.quantity }}</span>
                                    <button @click="increaseQuantity(line)" class="w-7 h-7 flex items-center justify-center font-black text-stone-700 hover:bg-stone-50 rounded-md">+</button>
                                </div>
                                <span class="w-16 text-right font-black text-stone-900 text-xs">{{ formatter.format(line.quantity * line.price) }}</span>
                            </div>

                            <div class="space-y-2">
                                <div v-if="line.notes.length > 0" class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="note in line.notes"
                                        :key="note"
                                        @click="removeNoteFromLine(line, note)"
                                        class="px-2 py-1 bg-brand-gold-light border border-brand-gold/30 rounded-md text-[9px] font-black text-brand-gold"
                                    >
                                        {{ note }} ×
                                    </button>
                                </div>
                                <div v-if="commonNoteSuggestions.length > 0" class="flex gap-1.5 overflow-x-auto scrollbar-hide">
                                    <button
                                        v-for="suggestion in commonNoteSuggestions"
                                        :key="suggestion.note"
                                        @click="addNoteToLine(line, suggestion.note)"
                                        class="px-2 py-1 bg-white hover:bg-stone-50 border border-stone-100 rounded-md whitespace-nowrap text-[9px] font-bold text-stone-600"
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
                                        class="min-w-0 flex-1 h-8 bg-white border border-stone-200 rounded-lg px-3 text-[10px] font-bold outline-none focus:ring-1 focus:ring-brand-gold"
                                    >
                                    <button type="submit" class="h-8 px-3 bg-brand-dark text-white rounded-lg text-[9px] font-black uppercase tracking-widest">OK</button>
                                </form>
                            </div>
                        </article>
                    </div>

                    <div class="p-6 bg-brand-light border-t border-brand-border space-y-5 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <span class="font-black text-stone-700 uppercase text-[9px] tracking-widest">Totaalbedrag</span>
                            <span class="font-black text-2xl text-brand-red">{{ formatter.format(orderTotal) }}</span>
                        </div>

                        <button
                            @click="checkoutOrder"
                            :disabled="isCheckingOut || orderLines.length === 0"
                            class="w-full h-12 bg-brand-gold text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-lg shadow-brand-gold/10 hover:bg-stone-800 active:scale-[0.98] transition-all disabled:opacity-40 flex items-center justify-center gap-2"
                        >
                            <span v-if="isCheckingOut" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                            {{ isCheckingOut ? 'Bezig...' : 'Bestelling Afrekenen' }}
                        </button>
                    </div>
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

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
