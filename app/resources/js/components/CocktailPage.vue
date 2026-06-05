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

    if (count === 1) {
        return '1 cocktail gevonden';
    }

    return `${count} cocktails gevonden`;
});

const fetchJson = async (path) => {
    const response = await fetch(`${API_BASE_URL}/${path}`);

    if (!response.ok) {
        throw new Error('Cocktails konden niet worden geladen.');
    }

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

        if (currentRequest !== requestSequence) {
            return;
        }

        drinks.value = (payload.drinks ?? []).map(normalizeDrink);
    } catch (error) {
        if (currentRequest !== requestSequence) {
            return;
        }

        drinks.value = [];
        errorMessage.value =
            error instanceof Error ? error.message : 'Cocktails konden niet worden geladen.';
    } finally {
        if (currentRequest === requestSequence) {
            isLoading.value = false;
        }
    }
};

const selectAlcoholFilter = (value) => {
    activeAlcoholFilter.value = value;
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
        errorMessage.value =
            error instanceof Error ? error.message : 'Cocktails konden niet worden geladen.';
        isLoading.value = false;
    }
});
</script>

<template>
    <section class="cocktail-page" aria-labelledby="cocktail-title">
        <div class="cocktail-header">
            <div>
                <p>Cocktail inspiratie</p>
                <h2 id="cocktail-title">Kies alvast een drankje voor straks</h2>
            </div>
        </div>

        <div class="cocktail-controls" aria-label="Cocktail filters">
            <div class="cocktail-tabs" role="tablist" aria-label="Alcohol filter">
                <button
                    v-for="tab in alcoholTabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="activeAlcoholFilter === tab.value"
                    :class="{ 'is-active': activeAlcoholFilter === tab.value }"
                    @click="selectAlcoholFilter(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <label class="cocktail-field">
                <span>Zoeken</span>
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Zoek bijvoorbeeld mojito"
                    autocomplete="off"
                >
            </label>

            <label class="cocktail-field">
                <span>Categorie</span>
                <select v-model="activeCategory">
                    <option
                        v-for="category in categoryOptions"
                        :key="category.value"
                        :value="category.value"
                    >
                        {{ category.label }}
                    </option>
                </select>
            </label>
        </div>

        <div class="cocktail-results-bar">
            <strong>{{ resultLabel }}</strong>
            <button type="button" @click="clearFilters">Filters wissen</button>
        </div>

        <p v-if="errorMessage" class="cocktail-error">{{ errorMessage }}</p>
        <p v-else-if="isLoading || isCategoryLoading" class="cocktail-empty">Cocktails laden...</p>
        <p v-else-if="visibleDrinks.length === 0" class="cocktail-empty">
            Geen cocktails gevonden met deze filters.
        </p>

        <div v-else class="cocktail-grid">
            <article v-for="drink in visibleDrinks" :key="drink.id" class="cocktail-card">
                <img
                    :src="`${drink.thumbnail}/medium`"
                    :alt="drink.name"
                    loading="lazy"
                >
                <div>
                    <h3>{{ drink.name }}</h3>
                    <p>{{ activeAlcoholFilter === 'Alcoholic' ? 'Alcoholisch' : 'Non-alcoholisch' }}</p>
                </div>
            </article>
        </div>
    </section>
</template>
