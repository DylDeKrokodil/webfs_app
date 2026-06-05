<script setup>
import LegacyPageShell from './LegacyPageShell.vue';

const menuPages = [
    {
        src: '/images/menukaarten/restaurant-menukaart-1-2.jpg',
        alt: 'Menukaart De Gouden Draak pagina 1',
    },
    {
        src: '/images/menukaarten/restaurant-menukaart-1.jpg',
        alt: 'Menukaart De Gouden Draak pagina 2',
    },
];
</script>

<template>
    <LegacyPageShell>
        <section class="legacy-menu-page" aria-label="Menukaart">
            <img
                v-for="menuPage in menuPages"
                :key="menuPage.src"
                class="legacy-menu-image"
                :src="menuPage.src"
                :alt="menuPage.alt"
            >
        </section>
    </LegacyPageShell>
</template>
