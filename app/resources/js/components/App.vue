<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from './AdminLayout.vue';
import AdminKassaPage from './AdminKassaPage.vue';
import AdminMenuPage from './AdminMenuPage.vue';
import AdminOverviewPage from './AdminOverviewPage.vue';
import AdminStatsPage from './AdminStatsPage.vue';
import AdminTablesPage from './AdminTablesPage.vue';
import ContactPage from './ContactPage.vue';
import HomePage from './HomePage.vue';
import MenuPage from './MenuPage.vue';
import NotFoundPage from './NotFoundPage.vue';
import OrderConfirmationPage from './OrderConfirmationPage.vue';
import ReviewPage from './ReviewPage.vue';
import TabletPage from './TabletPage.vue';
import ToastHost from './ToastHost.vue';

const currentPath = ref(window.location.pathname);

const pageComponents = {
    '/': HomePage,
    '/menukaart': MenuPage,
    '/contact': ContactPage,
    '/tablet': TabletPage,
    '/admin/kassa': AdminKassaPage,
    '/admin/menu': AdminMenuPage,
    '/admin/overzicht': AdminOverviewPage,
    '/admin/statistieken': AdminStatsPage,
    '/admin/tafels': AdminTablesPage,
};

const currentPage = computed(() => {
    if (/^\/bestelling\/WEB-[A-Z0-9]{4,10}$/.test(currentPath.value)) {
        return OrderConfirmationPage;
    }

    if (/^\/tablet\/\d+$/.test(currentPath.value)) {
        return TabletPage;
    }

    if (/^\/review\/[A-Za-z0-9]+$/.test(currentPath.value)) {
        return ReviewPage;
    }

    return pageComponents[currentPath.value] ?? NotFoundPage;
});

const isAdminPage = computed(() => currentPath.value.startsWith('/admin/'));
const activeAdminPage = computed(() => {
    const parts = currentPath.value.split('/');
    return parts[parts.length - 1] || 'menu';
});

const currentToken = computed(() => {
    return currentPath.value.split('/').pop();
});

onMounted(() => {
    window.addEventListener('popstate', () => {
        currentPath.value = window.location.pathname;
    });

    // Simple SPA navigation for <a> tags
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (link && link.href && link.href.startsWith(window.location.origin)) {
            const href = link.getAttribute('href');
            
            // Skip non-internal or special links (like PDFs, files, or logout)
            if (!href || href.startsWith('http') || href.includes('.') || href === '/logout' || href.startsWith('/api')) return;

            e.preventDefault();
            window.history.pushState(null, '', href);
            currentPath.value = window.location.pathname;
        }
    });
});
</script>

<template>
    <AdminLayout v-if="isAdminPage" :active-page="activeAdminPage">
        <component 
            :is="currentPage" 
            :token="currentToken" 
        />
    </AdminLayout>
    <template v-else>
        <component 
            :is="currentPage" 
            :token="currentToken" 
        />
    </template>
    <ToastHost />
</template>
