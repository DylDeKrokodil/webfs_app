<script setup>
import { computed } from 'vue';
import AdminMenuPage from './AdminMenuPage.vue';
import ContactPage from './ContactPage.vue';
import HomePage from './HomePage.vue';
import MenuPage from './MenuPage.vue';

const pageComponents = {
    '/': HomePage,
    '/menukaart': MenuPage,
    '/contact': ContactPage,
    '/admin/menu': AdminMenuPage,
};

const currentPage = computed(() => pageComponents[window.location.pathname] ?? HomePage);
</script>

<template>
    <component :is="currentPage" />
</template>
