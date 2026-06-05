<script setup>
const dragonSmall = '/images/dragon-small.png';
const dragonSmallFlipped = '/images/dragon-small-flipped.png';
const dragonLarge = '/images/dragon-large.png';

const navigationItems = [
    { label: 'Menukaart', href: '/api/menu-items' },
    { label: 'Nieuws', href: '/#nieuws' },
    { label: 'Contact', href: '/contact' },
];

const leftFrameBorders = {
    '2-2': ['border-l', 'border-t'],
    '2-3': ['border-r', 'border-t'],
    '2-4': ['border-r', 'border-b'],
    '3-2': ['border-l', 'border-b'],
    '3-3': ['border-all'],
    '3-4': ['border-all'],
    '4-2': ['border-r', 'border-b'],
    '4-3': ['border-all'],
    '5-2': ['border-l', 'border-r'],
    '6-2': ['border-r', 'border-t'],
    '6-3': ['border-all'],
    '7-2': ['border-l', 'border-t'],
    '7-3': ['border-all'],
    '7-4': ['border-all'],
    '8-2': ['border-l', 'border-b'],
    '8-3': ['border-r', 'border-b'],
    '8-4': ['border-r', 'border-t'],
};

const frameCells = Array.from({ length: 81 }, (_, index) => {
    const row = Math.floor(index / 9) + 1;
    const col = (index % 9) + 1;
    let borders = leftFrameBorders[`${row}-${col}`] ?? [];
    const classes = [];

    if (col >= 6 && col <= 8) {
        borders = leftFrameBorders[`${row}-${10 - col}`] ?? [];
        classes.push('is-right-mirror');
    }

    if (col === 5 && (row === 2 || row === 8)) {
        borders = ['border-t', 'border-b'];
    }

    classes.push(...borders);

    return {
        id: index,
        row,
        col,
        class: classes.join(' '),
    };
});
</script>

<template>
    <main class="legacy-page">
        <header class="legacy-topbar">
            <div class="legacy-brand legacy-brand-left" aria-label="De Gouden Draak">
                <img :src="dragonSmall" alt="">
                <span>DE GOUDEN DRAAK</span>
                <img :src="dragonSmallFlipped" alt="">
            </div>

            <a class="legacy-marquee" href="/#nieuws" aria-label="Bekijk de aanbiedingen van deze week">
                <span>Welkom bij De Gouden Draak. Klik op deze tekst om de aanbiedingen van deze week te zien!</span>
            </a>

            <div class="legacy-brand" aria-label="De Gouden Draak">
                <img :src="dragonSmall" alt="">
                <span>DE GOUDEN DRAAK</span>
                <img :src="dragonSmallFlipped" alt="">
            </div>
        </header>

        <section class="legacy-frame">
            <div class="legacy-frame-grid">
                <span
                    v-for="cell in frameCells"
                    :key="cell.id"
                    class="legacy-frame-cell"
                    :class="cell.class"
                    :style="{ gridRow: cell.row, gridColumn: cell.col }"
                    aria-hidden="true"
                ></span>

                <div class="legacy-frame-content">
                    <div class="legacy-hero" aria-labelledby="restaurant-title">
                        <img class="legacy-large-dragon legacy-large-dragon-left" :src="dragonLarge" alt="">

                        <div class="legacy-title-block">
                            <p>Chinees Indische Specialiteiten</p>
                            <h1 id="restaurant-title">De Gouden Draak</h1>

                            <nav class="legacy-nav" aria-label="Hoofdnavigatie">
                                <a v-for="item in navigationItems" :key="item.label" :href="item.href">
                                    {{ item.label }}
                                </a>
                            </nav>
                        </div>

                        <img class="legacy-large-dragon legacy-large-dragon-right" :src="dragonLarge" alt="">
                    </div>

                    <slot></slot>
                </div>
            </div>
        </section>
    </main>
</template>
