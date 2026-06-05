import { ref } from 'vue';

export const useAdminShell = () => ({
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    isSidebarOpen: ref(false),
});
