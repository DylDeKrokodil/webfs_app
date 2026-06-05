<script setup>
import { computed, onMounted, ref } from 'vue';
import { toastService } from '../services/toastService';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const categories = ref([]);
const items = ref([]);
const selectedId = ref(null);
const query = ref('');
const categoryFilter = ref('all');
const statusFilter = ref('all');
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');

const emptyForm = () => ({
    id: null,
    menu_category_id: '',
    number: '',
    suffix: '',
    name: '',
    description: '',
    price: '',
    is_active: true,
});

const form = ref(emptyForm());

const formatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

const activeItems = computed(() => items.value.filter((item) => item.is_active));
const inactiveItems = computed(() => items.value.filter((item) => !item.is_active));
const averagePrice = computed(() => {
    if (items.value.length === 0) return formatter.format(0);
    const total = items.value.reduce((sum, item) => sum + Number(item.price), 0);
    return formatter.format(total / items.value.length);
});

const visibleItems = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return items.value.filter((item) => {
        const matchesQuery = needle === '' || [item.display_number, item.name, item.description, item.category]
            .filter(Boolean).some((value) => String(value).toLowerCase().includes(needle));
        const matchesCategory = categoryFilter.value === 'all' || String(item.menu_category_id) === String(categoryFilter.value);
        const matchesStatus = statusFilter.value === 'all' || (statusFilter.value === 'active' && item.is_active) || (statusFilter.value === 'inactive' && !item.is_active);
        return matchesQuery && matchesCategory && matchesStatus;
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

const selectItem = (item) => {
    selectedId.value = item.id;
    form.value = {
        id: item.id,
        menu_category_id: item.menu_category_id,
        number: item.number ?? '',
        suffix: item.suffix ?? '',
        name: item.name,
        description: item.description ?? '',
        price: Number(item.price).toFixed(2),
        is_active: item.is_active,
    };
};

const startNewItem = () => {
    selectedId.value = null;
    form.value = { ...emptyForm(), menu_category_id: categories.value[0]?.id ?? '' };
};

const apiRequest = async (url, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers ?? {}),
        },
    });
    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        const validationMessage = payload.errors ? Object.values(payload.errors).flat().join(' ') : null;
        throw new Error(validationMessage || payload.message || 'Fout bij opslaan.');
    }
    return response.status === 204 ? null : response.json();
};

const loadMenu = async () => {
    isLoading.value = true;
    try {
        const payload = await apiRequest('/api/admin/menu-items');
        categories.value = payload.categories ?? [];
        items.value = payload.items ?? [];
        if (items.value.length > 0) selectItem(items.value[0]); else startNewItem();
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const saveItem = async () => {
    isSaving.value = true;
    const payload = {
        menu_category_id: Number(form.value.menu_category_id),
        number: form.value.number === '' ? null : Number(form.value.number),
        suffix: form.value.suffix || null,
        name: form.value.name,
        description: form.value.description || null,
        price: Number(form.value.price),
        is_active: Boolean(form.value.is_active),
    };
    try {
        const isExisting = Boolean(form.value.id);
        const response = await apiRequest(isExisting ? `/api/admin/menu-items/${form.value.id}` : '/api/admin/menu-items', {
            method: isExisting ? 'PATCH' : 'POST',
            body: JSON.stringify(payload),
        });
        const savedItem = response.item;
        const index = items.value.findIndex((item) => item.id === savedItem.id);
        if (index === -1) items.value.push(savedItem); else items.value[index] = savedItem;
        selectItem(savedItem);
        toastService.success('Gerecht opgeslagen.');
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isSaving.value = false;
    }
};

const deleteItem = async () => {
    if (!form.value.id) { startNewItem(); return; }
    isSaving.value = true;
    try {
        await apiRequest(`/api/admin/menu-items/${form.value.id}`, { method: 'DELETE' });
        items.value = items.value.filter((item) => item.id !== form.value.id);
        toastService.success('Gerecht verwijderd.');
        if (items.value.length > 0) selectItem(items.value[0]); else startNewItem();
    } catch (error) {
        toastService.error(error.message);
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadMenu);
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
                <a href="/admin/menu" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs bg-white/10 text-white shadow-inner">
                    <span>Menukaart</span>
                </a>
                <a href="/admin/kassa" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs text-stone-400 hover:bg-white/5 hover:text-white transition-all">
                    <span>Kassa</span>
                </a>
                <a href="/admin/tafels" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs text-stone-400 hover:bg-white/5 hover:text-white transition-all">
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
                    <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Menubeheer</p>
                    <h1 class="text-xl font-black leading-tight">Menukaart</h1>
                </div>
                <button @click="startNewItem" class="px-4 py-2 bg-brand-dark text-white rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-stone-800 transition-all shadow-md">
                    Nieuw Gerecht
                </button>
            </header>

            <div class="flex-1 p-6 overflow-y-auto">
                <!-- Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div v-for="(val, label) in { 'Gerechten': items.length, 'Actief': activeItems.length, 'Verborgen': inactiveItems.length, 'Gem. Prijs': averagePrice }" :key="label" class="bg-white border border-brand-border p-4 rounded-2xl shadow-sm">
                        <span class="block text-[9px] uppercase font-bold text-stone-400 tracking-wider mb-1">{{ label }}</span>
                        <strong class="block text-lg font-black text-stone-900">{{ val }}</strong>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Menu List -->
                    <section class="lg:col-span-7 space-y-4">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col h-[calc(100dvh-240px)]">
                            <div class="p-4 border-b border-stone-100 bg-brand-light/50 space-y-3 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Gerechtenlijst</h3>
                                    <span class="text-[10px] font-bold text-stone-400">{{ visibleItems.length }} resultaten</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <input v-model="query" type="search" placeholder="Zoek..." class="col-span-1 sm:col-span-1 h-9 bg-white border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                    <select v-model="categoryFilter" class="h-9 bg-white border border-stone-200 rounded-lg px-2 text-xs font-bold outline-none">
                                        <option value="all">Alle Categorieën</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                    </select>
                                    <select v-model="statusFilter" class="h-9 bg-white border border-stone-200 rounded-lg px-2 text-xs font-bold outline-none">
                                        <option value="all">Alle Statussen</option>
                                        <option value="active">Actief</option>
                                        <option value="inactive">Verborgen</option>
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
                                        @click="selectItem(item)"
                                        class="w-full flex items-center gap-4 p-3 text-left transition-all hover:bg-brand-light"
                                        :class="selectedId === item.id ? 'bg-[#FFF7ED] ring-1 ring-inset ring-brand-gold/30' : ''"
                                    >
                                        <span class="w-8 font-black text-xs text-brand-red text-center">{{ item.display_number || '-' }}</span>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-black text-stone-900 text-xs truncate leading-tight">{{ item.name }}</h4>
                                            <p class="text-[9px] font-medium text-stone-400 truncate mt-0.5">{{ item.description || 'Geen omschrijving' }}</p>
                                        </div>
                                        <div class="text-right flex items-center gap-3">
                                            <span class="font-black text-stone-900 text-xs">{{ formatter.format(item.price) }}</span>
                                            <span class="w-1.5 h-1.5 rounded-full" :class="item.is_active ? 'bg-green-500' : 'bg-stone-300'"></span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Editor -->
                    <aside class="lg:col-span-5 sticky top-0 self-start mb-6">
                        <div class="bg-white border border-brand-border rounded-2xl shadow-lg overflow-hidden flex flex-col h-[calc(100dvh-240px)]">
                            <div class="p-5 border-b border-stone-100 bg-brand-dark text-white flex justify-between items-center flex-shrink-0">
                                <div>
                                    <h3 class="font-black text-lg leading-none">{{ form.id ? 'Wijzigen' : 'Nieuw' }}</h3>
                                    <p class="text-[9px] uppercase font-bold text-stone-500 mt-1 tracking-widest">Gerechtsgegevens</p>
                                </div>
                                <span v-if="form.id" class="text-[10px] font-black text-stone-400">ID: {{ form.id }}</span>
                            </div>

                            <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                                <form id="menuForm" class="space-y-4" @submit.prevent="saveItem">
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] uppercase font-black text-stone-400 tracking-wider">Categorie</label>
                                        <select v-model="form.menu_category_id" required class="w-full h-9 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] uppercase font-black text-stone-400 tracking-wider">Nummer</label>
                                            <input v-model="form.number" type="number" class="w-full h-9 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] uppercase font-black text-stone-400 tracking-wider">Toevoeging</label>
                                            <input v-model="form.suffix" type="text" class="w-full h-9 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[9px] uppercase font-black text-stone-400 tracking-wider">Naam</label>
                                        <input v-model="form.name" type="text" required class="w-full h-9 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-[9px] uppercase font-black text-stone-400 tracking-wider">Omschrijving</label>
                                        <textarea v-model="form.description" rows="2" class="w-full bg-stone-50 border border-stone-200 rounded-lg p-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold"></textarea>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 items-end">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] uppercase font-black text-stone-400 tracking-wider">Prijs</label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 font-bold text-xs">€</span>
                                                <input v-model="form.price" type="number" step="0.01" required class="w-full h-9 bg-stone-50 border border-stone-200 rounded-lg pl-7 pr-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                            </div>
                                        </div>
                                        <label class="flex items-center gap-2 h-9 cursor-pointer group">
                                            <input v-model="form.is_active" type="checkbox" class="w-3.5 h-3.5 rounded border-stone-300 text-brand-gold focus:ring-brand-gold">
                                            <span class="text-[10px] font-black text-stone-600 group-hover:text-stone-900 transition-colors uppercase">Actief</span>
                                        </label>
                                    </div>
                                </form>
                            </div>

                            <div class="p-5 bg-brand-light border-t border-stone-100 flex gap-3 flex-shrink-0">
                                <button
                                    form="menuForm"
                                    type="submit"
                                    :disabled="isSaving"
                                    class="flex-1 h-10 bg-brand-gold text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-lg hover:bg-[#854d03] active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                                >
                                    <span v-if="isSaving" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                                    Opslaan
                                </button>
                                <button
                                    @click="deleteItem"
                                    :disabled="isSaving"
                                    class="px-4 h-10 bg-white border border-stone-200 text-red-600 rounded-xl font-black uppercase tracking-[0.15em] text-[10px] hover:bg-red-50 hover:border-red-200 transition-all active:scale-[0.98]"
                                >
                                    {{ form.id ? 'Wis' : 'X' }}
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
</style>
