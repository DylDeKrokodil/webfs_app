<script setup>
import { computed } from 'vue';
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
    if (/^\/bestelling\/WEB-[A-Z0-9]+$/.test(window.location.pathname)) {
        return OrderConfirmationPage;
    }

    if (/^\/tablet\/\d+$/.test(window.location.pathname)) {
        return TabletPage;
    }

    if (/^\/review\/[A-Za-z0-9]+$/.test(window.location.pathname)) {
        return ReviewPage;
    }

    return pageComponents[window.location.pathname] ?? NotFoundPage;
});
const currentToken = computed(() => {
    return window.location.pathname.split('/').pop();
});
</script>

<template>
    <component 
        :is="currentPage" 
        :token="currentToken" 
    />
    <ToastHost />
</template>
