<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const API_BASE_URL = 'https://www.thecocktaildb.com/api/json/v1/1';
const alcoholTabs = [
    { label: 'Alcoholisch', value: 'Alcoholic' },
    { label: 'Non-alcoholisch', value: 'Non_Alcoholic' },
];

const activeAlcoholFilter = ref('Alcoholic');
const activeCategory = ref('');
const categories = ref([]);
const categoryDrinkIds = ref(null);
const drinks = ref([]);
const searchQuery = ref('');
const isLoading = ref(true);
const isCategoryLoading = ref(false);
const errorMessage = ref('');

let requestSequence = 0;

const normalizeDrink = (drink) => ({
    id: drink.idDrink,
    name: drink.strDrink,
    thumbnail: drink.strDrinkThumb,
    category: drink.strCategory ?? '',
    alcoholic: drink.strAlcoholic ?? '',
});

const categoryOptions = computed(() => [
    { label: 'Alle categorieen', value: '' },
    ...categories.value.map((category) => ({
        label: category.strCategory,
        value: category.strCategory,
    })),
]);

const visibleDrinks = computed(() => {
    const categoryIds = categoryDrinkIds.value;

    return drinks.value.filter((drink) => {
        if (activeCategory.value && categoryIds && !categoryIds.has(drink.id)) {
            return false;
        }

        if (drink.alcoholic && drink.alcoholic !== activeAlcoholFilter.value) {
            return false;
        }

        if (drink.category && activeCategory.value && drink.category !== activeCategory.value) {
            return false;
        }

        return true;
    });
});

const resultLabel = computed(() => {
    const count = visibleDrinks.value.length;
    if (count === 1) return '1 cocktail gevonden';
    return `${count} cocktails gevonden`;
});

const fetchJson = async (path) => {
    const response = await fetch(`${API_BASE_URL}/${path}`);
    if (!response.ok) throw new Error('Kon geen gegevens laden.');
    return response.json();
};

const loadCategories = async () => {
    const payload = await fetchJson('list.php?c=list');
    categories.value = payload.drinks ?? [];
};

const loadCategoryDrinkIds = async () => {
    if (!activeCategory.value) {
        categoryDrinkIds.value = null;
        return;
    }
    isCategoryLoading.value = true;
    try {
        const category = encodeURIComponent(activeCategory.value.replaceAll(' ', '_'));
        const payload = await fetchJson(`filter.php?c=${category}`);
        categoryDrinkIds.value = new Set((payload.drinks ?? []).map((drink) => drink.idDrink));
    } finally {
        isCategoryLoading.value = false;
    }
};

const loadDrinks = async () => {
    const currentRequest = ++requestSequence;
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const query = searchQuery.value.trim();
        const payload = query
            ? await fetchJson(`search.php?s=${encodeURIComponent(query)}`)
            : await fetchJson(`filter.php?a=${activeAlcoholFilter.value}`);

        if (currentRequest !== requestSequence) return;
        drinks.value = (payload.drinks ?? []).map(normalizeDrink);
    } catch (error) {
        if (currentRequest !== requestSequence) return;
        drinks.value = [];
        errorMessage.value = error.message;
    } finally {
        if (currentRequest === requestSequence) isLoading.value = false;
    }
};

const clearFilters = () => {
    searchQuery.value = '';
    activeCategory.value = '';
    activeAlcoholFilter.value = 'Alcoholic';
};

watch([activeAlcoholFilter, searchQuery], loadDrinks);
watch(activeCategory, loadCategoryDrinkIds);

onMounted(async () => {
    try {
        await Promise.all([loadCategories(), loadCategoryDrinkIds(), loadDrinks()]);
    } catch (error) {
        errorMessage.value = error.message;
        isLoading.value = false;
    }
});
</script>

<template>
    <section class="space-y-6 animate-in fade-in duration-500">
        <div class="bg-white border border-[#D6D3D1] rounded-2xl p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 space-y-2 w-full">
                    <span class="text-[10px] uppercase font-black text-stone-400 tracking-widest">Type</span>
                    <div class="flex p-1 bg-stone-100 rounded-xl">
                        <button
                            v-for="tab in alcoholTabs"
                            :key="tab.value"
                            type="button"
                            @click="activeAlcoholFilter = tab.value"
                            class="flex-1 py-2 px-4 rounded-lg font-bold text-xs uppercase transition-all"
                            :class="activeAlcoholFilter === tab.value ? 'bg-white text-[#1C1917] shadow-sm' : 'text-stone-400 hover:text-stone-600'"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 space-y-2 w-full">
                    <span class="text-[10px] uppercase font-black text-stone-400 tracking-widest">Zoeken</span>
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Bijv. Mojito..."
                            class="w-full h-11 bg-stone-50 border border-stone-200 rounded-xl px-4 pl-10 font-bold text-stone-800 outline-none focus:ring-2 focus:ring-[#A16207] transition-all"
                        >
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </span>
                    </div>
                </div>

                <div class="flex-1 space-y-2 w-full">
                    <span class="text-[10px] uppercase font-black text-stone-400 tracking-widest">Categorie</span>
                    <select
                        v-model="activeCategory"
                        class="w-full h-11 bg-stone-50 border border-stone-200 rounded-xl px-3 font-bold text-stone-800 outline-none focus:ring-2 focus:ring-[#A16207] transition-all appearance-none"
                    >
                        <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-stone-100 pt-4">
                <span class="text-xs font-black text-stone-900 uppercase tracking-tight">{{ resultLabel }}</span>
                <button
                    @click="clearFilters"
                    class="text-[10px] font-black text-[#A16207] hover:underline uppercase"
                >
                    Filters wissen
                </button>
            </div>
        </div>

        <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm font-bold text-center">
            {{ errorMessage }}
        </div>
        <div v-else-if="isLoading || isCategoryLoading" class="py-20 text-center">
            <div class="w-10 h-10 border-4 border-stone-100 border-t-[#A16207] rounded-full animate-spin mx-auto"></div>
        </div>
        <div v-else-if="visibleDrinks.length === 0" class="py-20 text-center text-stone-400">
            <p class="font-black">Geen cocktails gevonden</p>
        </div>
        <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <article
                v-for="drink in visibleDrinks"
                :key="drink.id"
                class="bg-white border border-[#D6D3D1] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group"
            >
                <div class="aspect-square overflow-hidden bg-stone-100 relative">
                    <img
                        :src="`${drink.thumbnail}/medium`"
                        :alt="drink.name"
                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#A16207" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        </div>
                    </div>
                </div>
                <div class="p-4 space-y-1">
                    <h3 class="font-black text-stone-900 text-sm truncate leading-tight">{{ drink.name }}</h3>
                    <p class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">
                        {{ activeAlcoholFilter === 'Alcoholic' ? 'Alcoholisch' : 'Non-alcoholisch' }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
