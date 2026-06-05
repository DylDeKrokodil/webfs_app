<script setup>
import { computed } from 'vue';
import ContactPage from './ContactPage.vue';
import HomePage from './HomePage.vue';

const pageComponents = {
    '/': HomePage,
    '/contact': ContactPage,
};

const currentPage = computed(() => pageComponents[window.location.pathname] ?? HomePage);
</script>

<template>
    <component :is="currentPage" />
</template>
