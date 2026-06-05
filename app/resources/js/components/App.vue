<script setup>
import { computed } from 'vue';
import AdminKassaPage from './AdminKassaPage.vue';
import AdminMenuPage from './AdminMenuPage.vue';
import ContactPage from './ContactPage.vue';
import HomePage from './HomePage.vue';
import MenuPage from './MenuPage.vue';
import TabletPage from './TabletPage.vue';
import ToastHost from './ToastHost.vue';

const pageComponents = {
    '/': HomePage,
    '/menukaart': MenuPage,
    '/contact': ContactPage,
    '/tablet': TabletPage,
    '/admin/kassa': AdminKassaPage,
    '/admin/menu': AdminMenuPage,
};

const currentPage = computed(() => {
    if (/^\/tablet\/\d+$/.test(window.location.pathname)) {
        return TabletPage;
    }

    return pageComponents[window.location.pathname] ?? HomePage;
});
</script>

<template>
    <component :is="currentPage" />
    <ToastHost />
</template>
