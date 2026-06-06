<script setup>
import { computed, onMounted, ref } from 'vue';
import AnimatedNumber from './AnimatedNumber.vue';
import { useAdminShell } from '../composables/useAdminShell';
import { useMenuItems } from '../composables/useMenuItems';
import { currencyFormatter as formatter } from '../services/formatters';
import { toastService } from '../services/toastService';

const { csrfToken, isSidebarOpen, isCollapsed } = useAdminShell();

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

const {
    activeItems,
    inactiveItems,
    visibleItems,
    groupedItems,
} = useMenuItems({
    items,
    query,
    categoryFilter,
    statusFilter,
    searchableFields: ['display_number', 'name', 'description', 'category'],
});

const emptyState = computed(() => {
    if (statusFilter.value === 'inactive' && inactiveItems.value.length === 0) {
        return {
            title: 'Geen verborgen gerechten',
            copy: 'Alle gerechten staan momenteel actief op de menukaart.',
            action: 'Toon alle statussen',
        };
    }

    if (statusFilter.value === 'active' && activeItems.value.length === 0) {
        return {
            title: 'Geen actieve gerechten',
            copy: 'Er staan nog geen actieve gerechten op de menukaart.',
            action: 'Toon alle statussen',
        };
    }

    return {
        title: 'Geen resultaten',
        copy: 'Pas je zoekopdracht, categorie of statusfilter aan om gerechten te vinden.',
        action: 'Wis filters',
    };
});

const clearListFilters = () => {
    query.value = '';
    categoryFilter.value = 'all';
    statusFilter.value = 'all';
};

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
    const maxNumber = items.value.reduce((max, item) => Math.max(max, item.number ?? 0), 0);
    form.value = {
        ...emptyForm(),
        menu_category_id: categories.value[0]?.id ?? '',
        number: maxNumber + 1,
    };
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
    <!-- Workspace -->
    <section class="flex-1 min-w-0 min-h-0 flex flex-col">
        <header class="bg-white border-b border-brand-border px-6 py-4 z-40 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-4">
                <button @click="isSidebarOpen = true" class="lg:hidden p-2 -ml-2 text-stone-600 hover:bg-stone-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
                <div>
                    <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Menubeheer</p>
                    <h1 class="text-xl font-black leading-tight">Menukaart</h1>
                </div>
            </div>
            <button @click="startNewItem" class="px-4 py-2 bg-brand-dark text-white rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-stone-800 transition-all shadow-md shadow-brand-dark/10">
                Nieuw Gerecht
            </button>
        </header>

        <div class="flex-1 min-h-0 flex flex-col lg:flex-row overflow-y-auto lg:overflow-hidden custom-scrollbar">
            <!-- Metrics and List Area -->
            <div class="flex-shrink-0 lg:flex-1 lg:min-h-0 flex flex-col border-r border-brand-border bg-white">
                <!-- Metrics -->
                <div class="grid grid-cols-2 lg:grid-cols-3 border-b border-brand-border bg-stone-50/50 flex-shrink-0">
                    <div v-for="(val, label) in { 'Totaal Gerechten': items.length, 'Actieve Items': activeItems.length, 'Verborgen': inactiveItems.length }" :key="label" class="p-6 border-r border-brand-border last:border-r-0">
                        <span class="block text-[9px] uppercase font-black text-stone-600 mb-1">{{ label }}</span>
                        <p class="text-2xl font-black text-stone-900 leading-none">
                            <AnimatedNumber :value="val" />
                        </p>
                    </div>
                </div>

                <!-- Menu List -->
                <div class="flex-shrink-0 lg:flex-1 lg:min-h-0 flex flex-col">
                    <div class="p-6 border-b border-brand-border space-y-4 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-[10px] uppercase tracking-widest text-stone-600">Gerechtenlijst</h3>
                            <span class="text-[9px] font-black uppercase tracking-tighter text-brand-gold">{{ visibleItems.length }} RESULTATEN</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <input v-model="query" type="search" placeholder="Zoek..." class="h-9 bg-stone-50 border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                            <select v-model="categoryFilter" class="h-9 bg-stone-50 border border-stone-200 rounded-lg px-2 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                <option value="all">Alle Categorieën</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <select v-model="statusFilter" class="h-9 bg-stone-50 border border-stone-200 rounded-lg px-2 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold">
                                <option value="all">Alle Statussen</option>
                                <option value="active">Actief</option>
                                <option value="inactive">Verborgen</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="isLoading" class="p-12 text-center flex-shrink-0 lg:flex-1 lg:flex lg:items-center lg:justify-center">
                        <div class="w-8 h-8 border-3 border-stone-100 border-t-brand-gold rounded-full animate-spin"></div>
                    </div>
                    <div v-else-if="visibleItems.length === 0" class="flex-shrink-0 lg:flex-1 p-8 text-center lg:flex lg:flex-col lg:items-center lg:justify-center">
                        <h4 class="font-black text-[10px] uppercase tracking-widest text-stone-600 mb-2">{{ emptyState.title }}</h4>
                        <p class="max-w-xs text-[11px] font-bold text-stone-600 leading-relaxed mb-6">{{ emptyState.copy }}</p>
                        <button
                            type="button"
                            @click="clearListFilters"
                            class="h-9 px-4 rounded-lg bg-brand-dark text-white text-[9px] font-black uppercase tracking-widest hover:bg-stone-800 transition-all shadow-sm"
                        >
                            {{ emptyState.action }}
                        </button>
                    </div>
                    <div v-else class="divide-y divide-brand-border flex-shrink-0 lg:flex-1 lg:overflow-y-auto custom-scrollbar">
                        <div v-for="group in groupedItems" :key="group.category">
                            <div class="px-6 py-2 bg-stone-50 text-[9px] font-black uppercase text-stone-700 tracking-widest">{{ group.category }}</div>
                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                @click="selectItem(item)"
                                class="w-full flex items-center gap-4 px-6 py-3 text-left transition-all hover:bg-stone-50 group border-b border-stone-50 last:border-0"
                                :class="selectedId === item.id ? 'bg-brand-gold-light ring-1 ring-inset ring-brand-gold/30' : ''"
                            >
                                <span class="w-8 font-black text-xs text-brand-red text-center">{{ item.display_number || '-' }}</span>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-black text-stone-900 text-xs truncate leading-tight">{{ item.name }}</h4>
                                    <p class="text-[9px] font-bold text-stone-600 uppercase tracking-tighter mt-0.5">{{ item.description || 'Geen omschrijving' }}</p>
                                </div>
                                <div class="text-right flex items-center gap-3">
                                    <span class="font-black text-stone-900 text-xs">{{ formatter.format(item.price) }}</span>
                                    <span class="w-1.5 h-1.5 rounded-full" :class="item.is_active ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.4)]' : 'bg-stone-400'"></span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Editor -->
            <aside class="w-full lg:w-96 flex-shrink-0 flex flex-col bg-stone-50/30">
                <header class="p-6 border-b border-brand-border flex items-center justify-between bg-white flex-shrink-0">
                    <div>
                        <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Menubeheer</p>
                        <h2 class="text-lg font-black leading-tight">{{ form.id ? 'Wijzigen' : 'Nieuw' }}</h2>
                    </div>
                    <span v-if="form.id" class="text-[9px] font-black text-stone-600">ID: {{ form.id }}</span>
                </header>

                <div class="flex-shrink-0 lg:flex-1 lg:overflow-y-auto p-6 custom-scrollbar">
                    <form id="menuForm" class="space-y-5" @submit.prevent="saveItem">
                        <div class="space-y-1.5">
                            <label class="text-[9px] uppercase font-black text-stone-600 tracking-widest">Categorie</label>
                            <select v-model="form.menu_category_id" required class="w-full h-10 bg-white border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold shadow-sm">
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-[9px] uppercase font-black text-stone-600 tracking-widest">
                                    Nummer
                                    <span v-if="form.id" class="ml-1 text-[8px] font-bold text-stone-400 normal-case tracking-normal">(Niet aanpasbaar)</span>
                                </label>
                                <input
                                    v-model="form.number"
                                    type="number"
                                    :disabled="!!form.id"
                                    class="w-full h-10 bg-white border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold shadow-sm disabled:bg-stone-100 disabled:text-stone-400 disabled:cursor-not-allowed"
                                >
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[9px] uppercase font-black text-stone-600 tracking-widest">Suffix</label>
                                <input v-model="form.suffix" type="text" placeholder="bijv. A" class="w-full h-10 bg-white border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold shadow-sm">
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[9px] uppercase font-black text-stone-600 tracking-widest">Naam van gerecht</label>
                            <input v-model="form.name" type="text" required class="w-full h-10 bg-white border border-stone-200 rounded-lg px-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold shadow-sm">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[9px] uppercase font-black text-stone-600 tracking-widest">Omschrijving</label>
                            <textarea v-model="form.description" rows="3" class="w-full bg-white border border-stone-200 rounded-lg p-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold shadow-sm"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 items-end">
                            <div class="space-y-1.5">
                                <label class="text-[9px] uppercase font-black text-stone-600 tracking-widest">Prijs</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-500 font-bold text-xs">€</span>
                                    <input v-model="form.price" type="number" step="0.01" required class="w-full h-10 bg-white border border-stone-200 rounded-lg pl-7 pr-3 text-xs font-bold outline-none focus:ring-1 focus:ring-brand-gold shadow-sm">
                                </div>
                            </div>
                            <label class="flex items-center gap-2.5 h-10 cursor-pointer group px-1">
                                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-stone-300 text-brand-gold focus:ring-brand-gold">
                                <span class="text-[10px] font-black text-stone-600 group-hover:text-stone-900 transition-colors uppercase tracking-widest">Actief</span>
                            </label>
                        </div>
                    </form>
                </div>

                <div class="p-6 bg-brand-light border-t border-brand-border flex gap-3 flex-shrink-0">
                    <button
                        form="menuForm"
                        type="submit"
                        :disabled="isSaving"
                        class="flex-1 h-11 bg-brand-gold text-white rounded-xl font-black uppercase tracking-[0.15em] text-[10px] shadow-lg shadow-brand-gold/10 hover:bg-stone-800 active:scale-[0.98] transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <span v-if="isSaving" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                        Gerecht Opslaan
                    </button>
                    <button
                        @click="deleteItem"
                        :disabled="isSaving"
                        class="px-4 h-11 bg-white border border-stone-200 text-brand-red rounded-xl font-black uppercase tracking-widest text-[10px] hover:bg-red-50 hover:border-brand-red transition-all active:scale-[0.98]"
                        :title="form.id ? 'Gerecht verwijderen' : 'Annuleren'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                    </button>
                </div>
            </aside>
        </div>
    </section>
</template>

<style scoped>
</style>
