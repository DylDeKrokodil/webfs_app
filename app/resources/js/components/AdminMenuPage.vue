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
    if (items.value.length === 0) {
        return formatter.format(0);
    }

    const total = items.value.reduce((sum, item) => sum + Number(item.price), 0);

    return formatter.format(total / items.value.length);
});

const visibleItems = computed(() => {
    const needle = query.value.trim().toLowerCase();

    return items.value.filter((item) => {
        const matchesQuery =
            needle === '' ||
            [item.display_number, item.name, item.description, item.category]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(needle));
        const matchesCategory =
            categoryFilter.value === 'all' ||
            String(item.menu_category_id) === String(categoryFilter.value);
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'active' && item.is_active) ||
            (statusFilter.value === 'inactive' && !item.is_active);

        return matchesQuery && matchesCategory && matchesStatus;
    });
});

const groupedItems = computed(() => {
    const groups = new Map();

    visibleItems.value.forEach((item) => {
        if (!groups.has(item.category)) {
            groups.set(item.category, []);
        }

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
    form.value = {
        ...emptyForm(),
        menu_category_id: categories.value[0]?.id ?? '',
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
        const validationMessage = payload.errors
            ? Object.values(payload.errors).flat().join(' ')
            : null;

        throw new Error(validationMessage || payload.message || 'De wijziging kon niet worden opgeslagen.');
    }

    if (response.status === 204) {
        return null;
    }

    return response.json();
};

const loadMenu = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const payload = await apiRequest('/api/admin/menu-items');
        categories.value = payload.categories ?? [];
        items.value = payload.items ?? [];

        if (items.value.length > 0) {
            selectItem(items.value[0]);
        } else {
            startNewItem();
        }
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'De admin data kon niet worden geladen.';
    } finally {
        isLoading.value = false;
    }
};

const saveItem = async () => {
    isSaving.value = true;
    errorMessage.value = '';

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
        const response = await apiRequest(
            isExisting ? `/api/admin/menu-items/${form.value.id}` : '/api/admin/menu-items',
            {
                method: isExisting ? 'PATCH' : 'POST',
                body: JSON.stringify(payload),
            },
        );

        const savedItem = response.item;
        const index = items.value.findIndex((item) => item.id === savedItem.id);

        if (index === -1) {
            items.value.push(savedItem);
        } else {
            items.value[index] = savedItem;
        }

        selectItem(savedItem);
        toastService.success('Gerecht opgeslagen.', { title: 'Menukaart' });
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Opslaan mislukt.';
        toastService.error(errorMessage.value, { title: 'Opslaan mislukt' });
    } finally {
        isSaving.value = false;
    }
};

const deleteItem = async () => {
    if (!form.value.id) {
        startNewItem();
        return;
    }

    isSaving.value = true;
    errorMessage.value = '';

    try {
        await apiRequest(`/api/admin/menu-items/${form.value.id}`, { method: 'DELETE' });
        items.value = items.value.filter((item) => item.id !== form.value.id);
        toastService.success('Gerecht verwijderd.', { title: 'Menukaart' });

        if (items.value.length > 0) {
            selectItem(items.value[0]);
        } else {
            startNewItem();
        }
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Verwijderen mislukt.';
        toastService.error(errorMessage.value, { title: 'Verwijderen mislukt' });
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadMenu);
</script>

<template>
    <main class="admin-app">
        <aside class="admin-sidebar" aria-label="Admin navigatie">
            <a class="admin-brand" href="/admin/menu">
                <span>GD</span>
                <strong>Gouden Draak</strong>
            </a>

            <nav class="admin-nav">
                <a class="is-active" href="/admin/menu">Menukaart</a>
                <a href="/admin/kassa">Kassa</a>
            </nav>

            <form class="admin-logout" action="/logout" method="POST">
                <input type="hidden" name="_token" :value="csrfToken">
                <button type="submit">Log uit</button>
            </form>
        </aside>

        <section class="admin-workspace">
            <header class="admin-header">
                <div>
                    <p>Admin</p>
                    <h1>Menukaart beheer</h1>
                </div>
                <button class="admin-primary-action" type="button" @click="startNewItem">
                    Nieuw gerecht
                </button>
            </header>

            <div class="admin-metrics" aria-label="Menukaart statistieken">
                <div>
                    <span>Gerechten</span>
                    <strong>{{ items.length }}</strong>
                </div>
                <div>
                    <span>Actief</span>
                    <strong>{{ activeItems.length }}</strong>
                </div>
                <div>
                    <span>Verborgen</span>
                    <strong>{{ inactiveItems.length }}</strong>
                </div>
                <div>
                    <span>Gem. prijs</span>
                    <strong>{{ averagePrice }}</strong>
                </div>
            </div>

            <p v-if="errorMessage" class="admin-error">{{ errorMessage }}</p>

            <div class="admin-content">
                <section class="admin-list-panel" aria-labelledby="admin-list-title">
                    <div class="admin-panel-header">
                        <div>
                            <h2 id="admin-list-title">Gerechten</h2>
                            <p>{{ visibleItems.length }} zichtbaar met huidige filters</p>
                        </div>
                    </div>

                    <div class="admin-filters">
                        <input v-model="query" type="search" placeholder="Zoek op nummer, naam of categorie">
                        <select v-model="categoryFilter">
                            <option value="all">Alle categorieen</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <select v-model="statusFilter">
                            <option value="all">Alle statussen</option>
                            <option value="active">Actief</option>
                            <option value="inactive">Verborgen</option>
                        </select>
                    </div>

                    <p v-if="isLoading" class="admin-empty-state">Menukaart laden...</p>
                    <p v-else-if="visibleItems.length === 0" class="admin-empty-state">
                        Geen gerechten gevonden.
                    </p>

                    <div v-else class="admin-menu-groups">
                        <section
                            v-for="group in groupedItems"
                            :key="group.category"
                            class="admin-menu-group"
                        >
                            <h3>{{ group.category }}</h3>
                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                class="admin-menu-row"
                                :class="{ 'is-selected': item.id === selectedId }"
                                type="button"
                                @click="selectItem(item)"
                            >
                                <span class="admin-menu-code">{{ item.display_number || '-' }}</span>
                                <span class="admin-menu-name">
                                    <strong>{{ item.name }}</strong>
                                    <small>{{ item.description || 'Geen omschrijving' }}</small>
                                </span>
                                <span class="admin-menu-price">{{ formatter.format(item.price) }}</span>
                                <span class="admin-status" :class="{ 'is-muted': !item.is_active }">
                                    {{ item.is_active ? 'Actief' : 'Verborgen' }}
                                </span>
                            </button>
                        </section>
                    </div>
                </section>

                <aside class="admin-editor" aria-labelledby="admin-editor-title">
                    <div class="admin-panel-header">
                        <div>
                            <h2 id="admin-editor-title">
                                {{ form.id ? 'Gerecht wijzigen' : 'Nieuw gerecht' }}
                            </h2>
                            <p>Wijzigingen komen direct uit de admin database.</p>
                        </div>
                    </div>

                    <form class="admin-form" @submit.prevent="saveItem">
                        <label>
                            Categorie
                            <select v-model="form.menu_category_id" required>
                                <option value="" disabled>Kies categorie</option>
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </label>

                        <div class="admin-form-grid">
                            <label>
                                Nummer
                                <input v-model="form.number" type="number" min="1" max="9999">
                            </label>
                            <label>
                                Toevoeging
                                <input v-model="form.suffix" type="text" maxlength="10">
                            </label>
                        </div>

                        <label>
                            Naam
                            <input v-model="form.name" type="text" required maxlength="255">
                        </label>

                        <label>
                            Omschrijving
                            <textarea v-model="form.description" rows="4"></textarea>
                        </label>

                        <div class="admin-form-grid">
                            <label>
                                Prijs
                                <input v-model="form.price" type="number" min="0" step="0.01" required>
                            </label>
                            <label class="admin-toggle">
                                <input v-model="form.is_active" type="checkbox">
                                <span>Actief op menu</span>
                            </label>
                        </div>

                        <div class="admin-form-actions">
                            <button class="admin-save" type="submit" :disabled="isSaving">
                                {{ isSaving ? 'Opslaan...' : 'Opslaan' }}
                            </button>
                            <button class="admin-danger" type="button" :disabled="isSaving" @click="deleteItem">
                                {{ form.id ? 'Verwijderen' : 'Leegmaken' }}
                            </button>
                        </div>
                    </form>
                </aside>
            </div>
        </section>
    </main>
</template>
