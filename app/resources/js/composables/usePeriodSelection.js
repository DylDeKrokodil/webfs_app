import { computed, ref } from 'vue';

export const usePeriodSelection = () => {
    const formatInputDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const getWeekRange = (date) => {
        const start = new Date(date);
        const day = start.getDay() || 7;
        start.setDate(start.getDate() - day + 1);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        return { start, end };
    };

    const today = formatInputDate(new Date());
    const startDate = ref(today);
    const endDate = ref(today);
    const activePreset = ref('today');

    const periodLabel = computed(() => {
        if (startDate.value === endDate.value) return startDate.value;
        return `${startDate.value} t/m ${endDate.value}`;
    });

    const periodPresets = [
        { key: 'today', label: 'Vandaag', range: () => ({ start: new Date(), end: new Date() }) },
        { key: 'this_week', label: 'Deze week', range: () => getWeekRange(new Date()) },
        { key: 'last_week', label: 'Vorige week', range: () => {
            const d = new Date(); d.setDate(d.getDate() - 7); return getWeekRange(d);
        }},
        { key: 'this_month', label: 'Deze maand', range: () => {
            const now = new Date(); return { start: new Date(now.getFullYear(), now.getMonth(), 1), end: new Date(now.getFullYear(), now.getMonth() + 1, 0) };
        }},
        { key: 'last_month', label: 'Vorige maand', range: () => {
            const now = new Date(); return { start: new Date(now.getFullYear(), now.getMonth() - 1, 1), end: new Date(now.getFullYear(), now.getMonth(), 0) };
        }},
        { key: 'this_year', label: 'Dit jaar', range: () => {
            const now = new Date(); return { start: new Date(now.getFullYear(), 0, 1), end: new Date(now.getFullYear(), 11, 31) };
        }},
        { key: 'last_year', label: 'Vorig jaar', range: () => {
            const year = new Date().getFullYear() - 1; return { start: new Date(year, 0, 1), end: new Date(year, 11, 31) };
        }},
    ];

    const applyPreset = async (preset, callback) => {
        const range = preset.range();
        startDate.value = formatInputDate(range.start);
        endDate.value = formatInputDate(range.end);
        activePreset.value = preset.key;
        if (callback) await callback();
    };

    const markCustomPeriod = () => {
        activePreset.value = 'custom';
    };

    return {
        startDate,
        endDate,
        activePreset,
        periodLabel,
        periodPresets,
        applyPreset,
        markCustomPeriod,
        formatInputDate,
    };
};
