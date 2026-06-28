import { ref, watch } from 'vue';

const isCollapsed = ref(localStorage.getItem('admin_sidebar_collapsed') === 'true');
const isSidebarOpen = ref(false);

watch(isCollapsed, (val) => {
    localStorage.setItem('admin_sidebar_collapsed', val ? 'true' : 'false');
});

export const useAdminShell = () => ({
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    isSidebarOpen,
    isCollapsed,
});
