<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminSidebar from './AdminSidebar.vue';
import { toastService } from '../services/toastService';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const isSidebarOpen = ref(false);

const categories = ref([]);
const items = ref([]);
const query = ref('');
const categoryFilter = ref('all');
const orderLines = ref([]);
const commonNoteSuggestions = ref([]);
const customNoteInputs = ref({});
const isLoading = ref(true);
const isCheckingOut = ref(false);
const errorMessage = ref('');

const formatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

const activeItems = computed(() => items.value.filter((item) => item.is_active));
const visibleItems = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return activeItems.value.filter((item) => {
        const matchesQuery = needle === '' || [item.display_number, item.name, item.category]
            .filter(Boolean).some((value) => String(value).toLowerCase().includes(needle));
        const matchesCategory = categoryFilter.value === 'all' || String(item.menu_category_id) === String(categoryFilter.value);
        return matchesQuery && matchesCategory;
    });
});

const groupedItems = computed(() => {
    const groups = new Map();
    visibleItems.value.forEach((item) => {
        if (!groups.has(item.category)) groups.set(item.category, []);
        groups.get(item.category).push(item);
    });
    return Array.from(groups, ([category, groupItems]) => ({ category, items: groupItems }));
});

const lineCount = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity, 0));
const orderTotal = computed(() => orderLines.value.reduce((sum, line) => sum + line.quantity * Number(line.price), 0));

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

const cleanNote = (note) => note.replace(/\s+/g, ' ').trim();
const normalizeNote = (note) => cleanNote(note).toLowerCase();

const addItem = (item) => {
    const existingLine = orderLines.value.find((l) => l.id === item.id);
    if (existingLine) { existingLine.quantity += 1; return; }
    orderLines.value.push({
        id: item.id,
        display_number: item.display_number,
        name: item.name,
        price: Number(item.price),
        quantity: 1,
        notes: [],
    });
};

const increaseQuantity = (line) => { line.quantity += 1; };
const decreaseQuantity = (line) => {
    if (line.quantity <= 1) { orderLines.value = orderLines.value.filter((l) => l.id !== line.id); return; }
    line.quantity -= 1;
};

const addNoteToLine = (line, note) => {
    const cleanedNote = cleanNote(note);
    if (!cleanedNote) return;
    if (line.notes.some((existingNote) => normalizeNote(existingNote) === normalizeNote(cleanedNote))) return;
    if (line.notes.length >= 5) {
        toastService.error('Maximaal 5 opmerkingen per gerecht.');
        return;
    }

    line.notes.push(cleanedNote);
    customNoteInputs.value[line.id] = '';
};

const removeNoteFromLine = (line, note) => {
    line.notes = line.notes.filter((existingNote) => existingNote !== note);
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
        orderLines.value = [];
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isCheckingOut.value = false;
    }
};

onMounted(() => {
    loadMenu();
    loadNoteSuggestions();
});
</script>

<template>
    <main class="min-h-screen bg-brand-light text-brand-dark flex font-sans antialiased">
        <AdminSidebar :is-open="isSidebarOpen" active-page="kassa" :csrf-token="csrfToken" @close="isSidebarOpen = false" />

        <!-- Workspace -->
        <section class="flex-1 min-w-0 flex flex-col">
            <header class="bg-white border-b border-brand-border px-6 py-4 sticky top-0 z-40 flex items-center justify-between">
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

            <div class="flex-1 p-6 overflow-y-auto">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Kassa List -->
                    <section class="lg:col-span-7 space-y-4">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col h-[calc(100dvh-140px)]">
                            <div class="p-4 border-b border-stone-100 bg-brand-light/50 space-y-3 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Snel Zoeken</h3>
                                    <span class="text-[10px] font-bold text-stone-400">{{ visibleItems.length }} gerechten</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="relative">
                                        <input v-model="query" type="search" placeholder="Naam of nummer..." autofocus class="w-full h-10 bg-white border border-stone-200 rounded-lg px-3 pl-9 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                                        </span>
                                    </div>
                                    <select v-model="categoryFilter" class="h-10 bg-white border border-stone-200 rounded-lg px-2 text-xs font-bold outline-none">
                                        <option value="all">Alle Categorieën</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="isLoading" class="p-12 text-center flex-1">
                                <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin mx-auto"></div>
                            </div>
                            <div v-else class="divide-y divide-stone-100 flex-1 overflow-y-auto custom-scrollbar">
                                <div v-for="group in groupedItems" :key="group.category">
                                    <div class="px-4 py-2 bg-stone-50 text-[9px] font-black uppercase text-stone-400 tracking-widest">{{ group.category }}</div>
                                    <button
                                        v-for="item in group.items"
                                        :key="item.id"
                                        @click="addItem(item)"
                                        class="w-full flex items-center gap-4 p-3 text-left transition-all hover:bg-[#FFF7ED] group"
                                    >
                                        <span class="w-8 font-black text-xs text-brand-red text-center">{{ item.display_number || '-' }}</span>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-black text-stone-900 text-xs truncate leading-tight">{{ item.name }}</h4>
                                            <p class="text-[9px] font-medium text-stone-400 truncate mt-0.5">{{ item.category }}</p>
                                        </div>
                                        <div class="text-right flex items-center gap-4">
                                            <span class="font-black text-stone-900 text-xs">{{ formatter.format(item.price) }}</span>
                                            <span class="px-2 py-1 bg-brand-dark text-white text-[8px] font-black uppercase rounded opacity-0 group-hover:opacity-100 transition-opacity">Plus</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Cart -->
                    <aside class="lg:col-span-5 sticky top-0 self-start mb-6">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-lg overflow-hidden flex flex-col h-[calc(100dvh-140px)]">
                            <div class="p-5 border-b border-stone-100 bg-brand-dark text-white flex justify-between items-center flex-shrink-0">
                                <div>
                                    <h3 class="font-black text-lg leading-none">Bestelling</h3>
                                    <p class="text-[9px] uppercase font-bold text-stone-500 mt-1 tracking-widest">Overzicht</p>
                                </div>
                                <button v-if="orderLines.length > 0" @click="orderLines = []" class="text-[9px] font-black text-stone-500 hover:text-red-400 uppercase tracking-widest">Leeg</button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-5 space-y-4 custom-scrollbar">
                                <p v-if="orderLines.length === 0" class="text-stone-300 font-bold text-xs text-center italic py-10">Voeg items toe</p>
                                <article v-for="line in orderLines" :key="line.id" class="space-y-2 border-b border-stone-100 pb-4 last:border-b-0 last:pb-0">
                                    <div class="flex items-center gap-3 group">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-black text-stone-900 text-xs leading-tight truncate">{{ line.display_number }} {{ line.name }}</h4>
                                            <p class="text-[9px] font-bold text-stone-400 mt-0.5">{{ formatter.format(line.price) }} / stuk</p>
                                        </div>
                                        <div class="flex items-center bg-stone-50 rounded-lg p-0.5 border border-stone-100">
                                            <button @click="decreaseQuantity(line)" class="w-7 h-7 flex items-center justify-center font-black text-stone-400 hover:text-stone-900">-</button>
                                            <span class="w-7 text-center font-black text-[10px]">{{ line.quantity }}</span>
                                            <button @click="increaseQuantity(line)" class="w-7 h-7 flex items-center justify-center font-black text-stone-400 hover:text-stone-900">+</button>
                                        </div>
                                        <span class="w-16 text-right font-black text-stone-900 text-xs">{{ formatter.format(line.quantity * line.price) }}</span>
                                    </div>

                                    <div class="pl-0 sm:pl-1 space-y-2">
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
                                                class="px-2 py-1 bg-stone-50 hover:bg-stone-100 border border-stone-100 rounded-md whitespace-nowrap text-[9px] font-bold text-stone-500"
                                            >
                                                {{ suggestion.note }}
                                            </button>
                                        </div>
                                        <form class="flex gap-2" @submit.prevent="addNoteToLine(line, customNoteInputs[line.id] ?? '')">
                                            <input
                                                v-model="customNoteInputs[line.id]"
                                                type="text"
                                                maxlength="160"
                                                placeholder="Opmerking, bv. geen ui..."
                                                class="min-w-0 flex-1 h-8 bg-stone-50 border border-stone-100 rounded-lg px-3 text-[10px] font-bold outline-none focus:ring-1 focus:ring-brand-gold"
                                            >
                                            <button type="submit" class="h-8 px-3 bg-brand-dark text-white rounded-lg text-[9px] font-black uppercase tracking-widest">Toevoegen</button>
                                        </form>
                                    </div>
                                </article>
                            </div>

                            <div class="p-6 bg-brand-light border-t border-stone-100 space-y-5 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-stone-400 uppercase text-[9px] tracking-widest">Subtotaal</span>
                                    <span class="font-black text-2xl text-brand-red">{{ formatter.format(orderTotal) }}</span>
                                </div>

                                <button
                                    @click="checkoutOrder"
                                    :disabled="isCheckingOut || orderLines.length === 0"
                                    class="w-full h-12 bg-brand-gold text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-lg hover:bg-[#854d03] active:scale-[0.98] transition-all disabled:opacity-40 flex items-center justify-center gap-2"
                                >
                                    <span v-if="isCheckingOut" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    {{ isCheckingOut ? 'Bezig...' : 'Afrekenen' }}
                                </button>
                            </div>
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

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
