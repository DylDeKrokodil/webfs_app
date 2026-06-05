import { computed } from 'vue';

const groupItemsByCategory = (items) => {
    const groups = new Map();
    items.forEach((item) => {
        if (!groups.has(item.category)) groups.set(item.category, []);
        groups.get(item.category).push(item);
    });

    return Array.from(groups, ([category, groupItems]) => ({ category, items: groupItems }));
};

export const useMenuItems = ({
    items,
    query,
    categoryFilter,
    statusFilter = null,
    activeOnly = false,
    searchableFields = ['display_number', 'name', 'category'],
}) => {
    const activeItems = computed(() => items.value.filter((item) => item.is_active));
    const inactiveItems = computed(() => items.value.filter((item) => !item.is_active));

    const visibleItems = computed(() => {
        const needle = query.value.trim().toLowerCase();
        const sourceItems = activeOnly ? activeItems.value : items.value;

        return sourceItems.filter((item) => {
            const matchesQuery = needle === '' || searchableFields
                .map((field) => item[field])
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(needle));
            const matchesCategory = categoryFilter.value === 'all' || String(item.menu_category_id) === String(categoryFilter.value);
            const matchesStatus = !statusFilter || statusFilter.value === 'all'
                || (statusFilter.value === 'active' && item.is_active)
                || (statusFilter.value === 'inactive' && !item.is_active);

            return matchesQuery && matchesCategory && matchesStatus;
        });
    });

    const groupedItems = computed(() => groupItemsByCategory(visibleItems.value));

    return {
        activeItems,
        inactiveItems,
        visibleItems,
        groupedItems,
    };
};
