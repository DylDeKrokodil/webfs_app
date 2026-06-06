<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMenuSync } from '../composables/useMenuSync';
import { useCart } from '../composables/useCart';
import LegacyPageShell from './LegacyPageShell.vue';
import { currencyFormatter as formatter } from '../services/formatters';
import { fetchPublicMenuItems } from '../services/menuApi';

const { t, locale } = useI18n();

const FAVORITES_COOKIE_NAME = 'gouden_draak_favorite_menu_items';
const FAVORITES_COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

const items = ref([]);
const favoriteIds = ref(new Set());
const activeTab = ref('menu');
const menuSortMode = ref('default');
const favoritesSortMode = ref('number');
const isLoading = ref(true);
const errorMessage = ref('');
const isSubmitting = ref(false);

const { handleMenuItemUpdated } = useMenuSync(items);
const {
    items: cartItems,
    count: cartCount,
    total: cartTotal,
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart
} = useCart();

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

watch(locale, () => {
    loadMenu();
});

const loadMenu = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        items.value = await fetchPublicMenuItems();
    } catch (error) {
        errorMessage.value = error.message || t('menu.errors.load_failed');
    } finally {
        isLoading.value = false;
    }
};

const checkout = async () => {
    if (cartItems.value.length === 0) return;

    isSubmitting.value = true;
    try {
        const response = await fetch('/api/takeaway/orders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                'X-Locale': locale.value
            },
            body: JSON.stringify({
                items: cartItems.value.map((i) => ({ id: i.id, quantity: i.quantity })),
            }),
        });

        const data = await response.json();
        if (!response.ok) throw new Error(data.message || t('menu.errors.checkout_failed'));

        clearCart();
        window.location.href = `/bestelling/${data.token}`;
    } catch (error) {
        alert(error.message);
    } finally {
        isSubmitting.value = false;
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
        <section class="legacy-menu-page" :aria-label="t('nav.menu')">
            <div class="legacy-menu-panel">
                <header class="legacy-menu-header">
                    <div>
                        <p>{{ t('menu.current_menu') }}</p>
                        <h2>{{ t('menu.database_dishes') }}</h2>
                    </div>

                    <a class="legacy-menu-download" href="/menukaart.pdf">
                        {{ t('menu.download_pdf') }}
                    </a>
                </header>

                <div class="legacy-menu-tabs" role="tablist" :aria-label="t('menu.current_menu')">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'menu'"
                        :aria-controls="'tabpanel-menu'"
                        :id="'tab-menu'"
                        :class="{ 'is-active': activeTab === 'menu' }"
                        @click="activeTab = 'menu'"
                    >
                        {{ t('menu.tabs.menu') }}
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'favorites'"
                        :aria-controls="'tabpanel-favorites'"
                        :id="'tab-favorites'"
                        :class="{ 'is-active': activeTab === 'favorites' }"
                        @click="activeTab = 'favorites'"
                    >
                        {{ t('menu.tabs.favorites') }}
                        <span>{{ favoriteItems.length }}</span>
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'cart'"
                        :aria-controls="'tabpanel-cart'"
                        :id="'tab-cart'"
                        :class="{ 'is-active': activeTab === 'cart' }"
                        @click="activeTab = 'cart'"
                    >
                        {{ t('menu.tabs.cart') }}
                        <span v-if="cartCount > 0">{{ cartCount }}</span>
                    </button>
                </div>

                <div v-if="activeTab === 'menu'" id="tabpanel-menu" role="tabpanel" aria-labelledby="tab-menu">
                    <div class="legacy-menu-toolbar">
                        <label>
                            {{ t('menu.sorting.label_menu') }}
                            <select v-model="menuSortMode">
                                <option value="default">{{ t('menu.sorting.default') }}</option>
                                <option value="favorites-first">{{ t('menu.sorting.favorites_first') }}</option>
                                <option value="favorites-alpha">{{ t('menu.sorting.favorites_alpha') }}</option>
                            </select>
                        </label>
                    </div>

                    <p v-if="isLoading" class="legacy-menu-state">{{ t('menu.states.loading') }}</p>
                    <p v-else-if="errorMessage" class="legacy-menu-state is-error">{{ errorMessage }}</p>

                    <div v-else class="legacy-menu-list">
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
                                <span>{{ t('menu.actions.favorite') }}</span>
                            </label>

                            <div class="legacy-menu-code">{{ item.display_number || '-' }}</div>

                            <div class="legacy-menu-copy">
                                <h3>{{ item.name }}</h3>
                                <p v-if="item.description">{{ item.description }}</p>
                                <small>{{ item.category }}</small>
                            </div>

                            <div class="legacy-menu-actions">
                                <div class="legacy-menu-price">{{ formatter.format(item.price) }}</div>
                                <button
                                    @click="addToCart(item)"
                                    class="legacy-menu-add-btn"
                                >
                                    {{ t('menu.actions.add_to_cart') }}
                                </button>
                            </div>
                        </article>
                    </div>
                </div>

                <div v-else-if="activeTab === 'favorites'" id="tabpanel-favorites" role="tabpanel" aria-labelledby="tab-favorites">
                    <div class="legacy-menu-toolbar">
                        <label>
                            {{ t('menu.sorting.label_favorites') }}
                            <select v-model="favoritesSortMode">
                                <option value="number">{{ t('menu.sorting.by_number') }}</option>
                                <option value="alpha">{{ t('menu.sorting.alpha') }}</option>
                            </select>
                        </label>
                    </div>

                    <p v-if="isLoading" class="legacy-menu-state">{{ t('menu.states.loading') }}</p>
                    <p v-else-if="errorMessage" class="legacy-menu-state is-error">{{ errorMessage }}</p>

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
                                <span>{{ t('menu.actions.favorite') }}</span>
                            </label>

                            <div class="legacy-menu-code">{{ item.display_number || '-' }}</div>

                            <div class="legacy-menu-copy">
                                <h3>{{ item.name }}</h3>
                                <p v-if="item.description">{{ item.description }}</p>
                                <small>{{ item.category }}</small>
                            </div>

                            <div class="legacy-menu-actions">
                                <div class="legacy-menu-price">{{ formatter.format(item.price) }}</div>
                                <button
                                    @click="addToCart(item)"
                                    class="legacy-menu-add-btn"
                                >
                                    {{ t('menu.actions.add_to_cart') }}
                                </button>
                            </div>
                        </article>
                    </div>
                    <p v-else class="legacy-menu-state">
                        {{ t('menu.states.empty_favorites') }}
                    </p>
                </div>

                <div v-else-if="activeTab === 'cart'" id="tabpanel-cart" role="tabpanel" aria-labelledby="tab-cart" class="legacy-menu-list">
                    <div v-if="cartItems.length > 0" class="legacy-cart-container">
                        <article
                            v-for="item in cartItems"
                            :key="item.id"
                            class="legacy-menu-row"
                        >
                            <div class="legacy-menu-code">{{ item.number || '-' }}</div>
                            <div class="legacy-menu-copy">
                                <h3>{{ item.name }}</h3>
                                <p>{{ t('common.price_per_piece', { price: formatter.format(item.price) }) }}</p>
                            </div>
                            
                            <div class="legacy-cart-actions">
                                <div class="legacy-quantity-selector">
                                    <button @click="updateQuantity(item.id, -1)" :aria-label="t('menu.actions.less')">-</button>
                                    <span>{{ item.quantity }}</span>
                                    <button @click="updateQuantity(item.id, 1)" :aria-label="t('menu.actions.more')">+</button>
                                </div>
                                <div class="legacy-menu-price">{{ formatter.format(item.price * item.quantity) }}</div>
                                <button 
                                    @click="removeFromCart(item.id)" 
                                    class="legacy-cart-remove" 
                                    :title="t('menu.actions.remove')"
                                    :aria-label="t('menu.actions.remove')"
                                >
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </article>

                        <div class="legacy-cart-summary">
                            <div class="legacy-total-row">
                                <span class="label">{{ t('menu.cart.total') }}</span>
                                <span class="value">{{ formatter.format(cartTotal) }}</span>
                            </div>
                            <button
                                @click="checkout"
                                :disabled="isSubmitting"
                                class="legacy-checkout-btn"
                            >
                                {{ isSubmitting ? t('menu.actions.submitting') : t('menu.actions.place_order') }}
                            </button>
                        </div>
                    </div>
                    <p v-else class="legacy-menu-state">
                        {{ t('menu.states.empty_cart') }}
                    </p>
                </div>
            </div>
        </section>
    </LegacyPageShell>
</template>

<style scoped>
.legacy-menu-tabs {
    display: flex;
    gap: 8px;
    margin-top: 18px;
    border-bottom: 1px solid #e5d7ba;
}

.legacy-menu-tabs button {
    appearance: none;
    border: 1px solid #d7c39c;
    border-bottom: 0;
    background: #f5ead2;
    color: #4b3327;
    cursor: pointer;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 15px;
    font-weight: 800;
    padding: 10px 16px;
    transition: background 0.2s;
}

.legacy-menu-tabs button:focus {
    outline: 2px solid #7f1d1d;
    outline-offset: -2px;
}

.legacy-menu-tabs button.is-active {
    background: #7f1d1d;
    color: #fff7d6;
}

.legacy-menu-tabs span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    margin-left: 6px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.28);
    font-size: 12px;
    line-height: 1.4;
    padding: 1px 6px;
}

.is-active span {
    background: rgba(0, 0, 0, 0.2);
}

.legacy-menu-list {
    display: grid;
    gap: 8px;
    margin-top: 16px;
}

.legacy-menu-row {
    display: grid;
    grid-template-columns: minmax(86px, auto) 58px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    border: 1px solid #eadbbd;
    background: #fffdf6;
    padding: 12px;
}

.legacy-menu-row.is-favorite {
    border-color: #d7b56d;
    background: #fff7df;
}

.legacy-menu-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.legacy-menu-add-btn {
    appearance: none;
    border: 2px solid #d7b56d;
    background: #7f1d1d;
    color: #fff7d6;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    padding: 6px 12px;
    cursor: pointer;
    transition: background 0.2s;
}

.legacy-menu-add-btn:hover {
    background: #5f1515;
}

.legacy-menu-add-btn:focus {
    outline: 2px solid #7f1d1d;
    outline-offset: 2px;
}

.legacy-checkout-btn {
    appearance: none;
    border: 2px solid #d7b56d;
    background: #7f1d1d;
    color: #fff7d6;
    font-size: 16px;
    font-weight: 800;
    text-transform: uppercase;
    padding: 14px 28px;
    cursor: pointer;
    transition: background 0.2s;
    width: 100%;
}

.legacy-checkout-btn:hover:not(:disabled) {
    background: #5f1515;
}

.legacy-checkout-btn:focus:not(:disabled) {
    outline: 2px solid #7f1d1d;
    outline-offset: 4px;
}

.legacy-checkout-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.legacy-cart-footer {
    border-top: 1px solid #e5d7ba;
}

.legacy-cart-actions {
    display: flex;
    align-items: center;
    gap: 20px;
    justify-content: flex-end;
}

.legacy-quantity-selector {
    display: inline-flex;
    align-items: center;
    border: 1px solid #d7c39c;
    background: #ffffff;
    overflow: hidden;
}

.legacy-quantity-selector button {
    width: 32px;
    height: 32px;
    border: none;
    background: #f5ead2;
    color: #4b3327;
    font-weight: 900;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.legacy-quantity-selector button:hover {
    background: #e5d7ba;
}

.legacy-quantity-selector button:focus {
    outline: 2px solid #7f1d1d;
    outline-offset: -2px;
    z-index: 1;
}

.legacy-quantity-selector span {
    width: 36px;
    text-align: center;
    font-weight: 800;
    font-size: 14px;
    color: #1d1714;
}

.legacy-cart-remove {
    appearance: none;
    border: none;
    background: transparent;
    color: #7f1d1d;
    cursor: pointer;
    padding: 8px;
    border-radius: 4px;
    transition: background 0.2s;
    display: flex;
}

.legacy-cart-remove:hover {
    background: #fff1f1;
    color: #991b1b;
}

.legacy-cart-remove:focus {
    outline: 2px solid #7f1d1d;
    outline-offset: 2px;
}

.legacy-cart-summary {
    margin-top: 32px;
    padding: 24px;
    background: #fff7df;
    border: 1px solid #d7b56d;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 20px;
}

.legacy-total-row {
    display: flex;
    align-items: baseline;
    gap: 16px;
}

.legacy-total-row .label {
    font-weight: 900;
    color: #594337;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.1em;
}

.legacy-total-row .value {
    font-size: 32px;
    font-weight: 900;
    color: #7f1d1d;
    font-family: Arial, Helvetica, sans-serif;
}

@media (max-width: 768px) {
    .legacy-menu-row {
        grid-template-columns: 58px 1fr;
        gap: 12px;
    }
    
    .legacy-menu-code {
        grid-row: 1;
        grid-column: 1;
    }
    
    .legacy-favorite-toggle {
        grid-row: 1;
        grid-column: 2;
        justify-self: end;
    }
    
    .legacy-menu-copy {
        grid-row: 2;
        grid-column: 1 / span 2;
    }
    
    .legacy-menu-actions, .legacy-cart-actions {
        grid-row: 3;
        grid-column: 1 / span 2;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #eadbbd;
        padding-top: 12px;
        margin-top: 4px;
    }
    
    .legacy-cart-actions {
        gap: 10px;
    }
}
</style>
