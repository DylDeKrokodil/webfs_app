<script setup>
import { useI18n } from 'vue-i18n';
import LegacyPageShell from './LegacyPageShell.vue';

const { t } = useI18n();

const offerChoices = [
    ['home.dishes.koe_loe_yuk', 'home.dishes.foe_yong_hai'],
    ['home.dishes.tjap_tjoy', 'home.dishes.prawns_garlic'],
    ['home.dishes.babi_pangang', 'home.dishes.chicken_black_bean'],
];
</script>

<template>
    <LegacyPageShell>
        <section id="nieuws" class="legacy-offer">
            <p>
                {{ t('home.intro_p1') }}
                {{ t('home.intro_p2') }}
            </p>

            <h2>{{ t('home.student_offer_title') }}</h2>
            <h3>{{ t('home.student_offer_subtitle') }}</h3>
            <p>{{ t('home.student_offer_instruction') }}</p>

            <dl>
                <template v-for="row in offerChoices" :key="row.join('-')">
                    <div>{{ t(row[0]) }}</div>
                    <div>{{ t(row[1]) }}</div>
                </template>
            </dl>

            <p>{{ t('home.student_offer_sides') }}</p>
            <strong>{{ t('home.student_offer_price', { price: '€21,00' }) }}</strong>
        </section>

        <p class="legacy-contact-link">
            <a href="/contact">{{ t('home.to_contact') }}</a>
        </p>
    </LegacyPageShell>
</template>
