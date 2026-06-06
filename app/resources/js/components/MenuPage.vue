<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useMenuSync } from '../composables/useMenuSync';
import LegacyPageShell from './LegacyPageShell.vue';
import { currencyFormatter as formatter } from '../services/formatters';
import { fetchPublicMenuItems } from '../services/menuApi';

const FAVORITES_COOKIE_NAME = 'gouden_draak_favorite_menu_items';
const FAVORITES_COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

const items = ref([]);
const favoriteIds = ref(new Set());
const activeTab = ref('menu');
const menuSortMode = ref('default');
const favoritesSortMode = ref('number');
const isLoading = ref(true);
const errorMessage = ref('');

const { handleMenuItemUpdated } = useMenuSync(items);

const parseNumber = (item) => {
    const parsed = Number(item.number);
    return Number.isFinite(parsed) ? parsed : Number.MAX_SAFE_INTEGER;
};

const compareByMenuNumber = (left, right) => {
    const numberComparison = parseNumber(left) - parseNumber(right);
    if (numberComparison !== 0) return numberComparison;

    return [
        String(left.suffix ?? '').localeCompare(String(right.suffix ?? ''), 'nl-NL', { numeric: true }),
        String(left.name ?? '').localeCompare(String(right.name ?? ''), 'nl-NL', { sensitivity: 'base' }),
    ].find((comparison) => comparison !== 0) ?? 0;
};

const compareByName = (left, right) => String(left.name ?? '')
    .localeCompare(String(right.name ?? ''), 'nl-NL', { sensitivity: 'base', numeric: true });

const isFavorite = (item) => favoriteIds.value.has(Number(item.id));

const sortedMenuItems = computed(() => {
    if (menuSortMode.value === 'favorites-first') {
        return [...items.value].sort((left, right) => {
            const favoriteComparison = Number(isFavorite(right)) - Number(isFavorite(left));
            if (favoriteComparison !== 0) return favoriteComparison;

            return compareByMenuNumber(left, right);
        });
    }

    if (menuSortMode.value === 'favorites-alpha') {
        const favorites = items.value
            .filter((item) => isFavorite(item))
            .sort(compareByName);
        const rest = items.value.filter((item) => !isFavorite(item));

        return [...favorites, ...rest];
    }

    return items.value;
});

const favoriteItems = computed(() => {
    const favorites = items.value.filter((item) => isFavorite(item));
    return [...favorites].sort(favoritesSortMode.value === 'alpha' ? compareByName : compareByMenuNumber);
});

const readFavoriteCookie = () => {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${FAVORITES_COOKIE_NAME}=`));

    if (!cookie) return new Set();

    try {
        const value = decodeURIComponent(cookie.split('=').slice(1).join('='));
        const ids = JSON.parse(value);

        if (!Array.isArray(ids)) return new Set();

        return new Set(ids.map((id) => Number(id)).filter((id) => Number.isInteger(id) && id > 0));
    } catch {
        return new Set();
    }
};

const writeFavoriteCookie = () => {
    const ids = [...favoriteIds.value].sort((left, right) => left - right);
    document.cookie = [
        `${FAVORITES_COOKIE_NAME}=${encodeURIComponent(JSON.stringify(ids))}`,
        `Max-Age=${FAVORITES_COOKIE_MAX_AGE}`,
        'Path=/',
        'SameSite=Lax',
    ].join('; ');
};

const toggleFavorite = (item) => {
    const nextFavorites = new Set(favoriteIds.value);
    const id = Number(item.id);

    if (nextFavorites.has(id)) {
        nextFavorites.delete(id);
    } else {
        nextFavorites.add(id);
    }

    favoriteIds.value = nextFavorites;
    writeFavoriteCookie();
};

const loadMenu = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        items.value = await fetchPublicMenuItems();
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const setupRealtime = () => {
    if (!window.Echo) return;

    window.Echo.channel('menu-updates')
        .listen('.MenuItemUpdated', handleMenuItemUpdated);
};

onMounted(() => {
    favoriteIds.value = readFavoriteCookie();
    loadMenu();
    setupRealtime();
});
</script>

<template>
    <LegacyPageShell>
        <section class="legacy-menu-page" aria-label="Menukaart">
            <div class="legacy-menu-panel">
                <header class="legacy-menu-header">
                    <div>
                        <p>Actuele menukaart</p>
                        <h2>Gerechten uit de database</h2>
                    </div>

                    <a class="legacy-menu-download" href="/menukaart.pdf">
                        Download PDF
                    </a>
                </header>

                <div class="legacy-menu-tabs" role="tablist" aria-label="Menukaart tabs">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'menu'"
                        :class="{ 'is-active': activeTab === 'menu' }"
                        @click="activeTab = 'menu'"
                    >
                        Menu
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'favorites'"
                        :class="{ 'is-active': activeTab === 'favorites' }"
                        @click="activeTab = 'favorites'"
                    >
                        Favorieten
                        <span>{{ favoriteItems.length }}</span>
                    </button>
                </div>

                <div v-if="activeTab === 'menu'" class="legacy-menu-toolbar">
                    <label>
                        Sorteer menu
                        <select v-model="menuSortMode">
                            <option value="default">Standaard volgorde</option>
                            <option value="favorites-first">Favorieten bovenaan op nummer</option>
                            <option value="favorites-alpha">Favorieten alfabetisch bovenaan</option>
                        </select>
                    </label>
                </div>

                <div v-else class="legacy-menu-toolbar">
                    <label>
                        Sorteer favorieten
                        <select v-model="favoritesSortMode">
                            <option value="number">Op nummer</option>
                            <option value="alpha">Alfabetisch</option>
                        </select>
                    </label>
                </div>

                <p v-if="isLoading" class="legacy-menu-state">Menukaart laden...</p>
                <p v-else-if="errorMessage" class="legacy-menu-state is-error">{{ errorMessage }}</p>

                <div v-else-if="activeTab === 'menu'" class="legacy-menu-list">
                    <article
                        v-for="item in sortedMenuItems"
                        :key="item.id"
                        class="legacy-menu-row"
                        :class="{ 'is-favorite': isFavorite(item) }"
                    >
                        <label class="legacy-favorite-toggle">
                            <input
                                type="checkbox"
                                :checked="isFavorite(item)"
                                @change="toggleFavorite(item)"
                            >
                            <span>Favoriet</span>
                        </label>

                        <div class="legacy-menu-code">{{ item.display_number || '-' }}</div>

                        <div class="legacy-menu-copy">
                            <h3>{{ item.name }}</h3>
                            <p v-if="item.description">{{ item.description }}</p>
                            <small>{{ item.category }}</small>
                        </div>

                        <div class="legacy-menu-price">{{ formatter.format(item.price) }}</div>
                    </article>
                </div>

                <div v-else-if="favoriteItems.length > 0" class="legacy-menu-list">
                    <article
                        v-for="item in favoriteItems"
                        :key="item.id"
                        class="legacy-menu-row is-favorite"
                    >
                        <label class="legacy-favorite-toggle">
                            <input
                                type="checkbox"
                                checked
                                @change="toggleFavorite(item)"
                            >
                            <span>Favoriet</span>
                        </label>

                        <div class="legacy-menu-code">{{ item.display_number || '-' }}</div>

                        <div class="legacy-menu-copy">
                            <h3>{{ item.name }}</h3>
                            <p v-if="item.description">{{ item.description }}</p>
                            <small>{{ item.category }}</small>
                        </div>

                        <div class="legacy-menu-price">{{ formatter.format(item.price) }}</div>
                    </article>
                </div>

                <p v-else class="legacy-menu-state">
                    Je hebt nog geen favoriete gerechten aangevinkt.
                </p>
            </div>
        </section>
    </LegacyPageShell>
</template>
