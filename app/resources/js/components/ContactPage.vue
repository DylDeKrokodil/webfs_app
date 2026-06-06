<script setup>
import { useI18n } from 'vue-i18n';
import LegacyPageShell from './LegacyPageShell.vue';

const { t } = useI18n();
const contactMap = '/images/contact-map.png';
</script>

<template>
    <LegacyPageShell>
        <section class="legacy-contact-page" aria-labelledby="contact-title">
            <div class="legacy-contact-card">
                <h2 id="contact-title">
                    {{ t('contact.find_us') }}
                </h2>

                <address>
                    Onderwijsboulevard 215, kamer OG112<br>
                    5223 DE 's-Hertogenbosch
                </address>
            </div>

            <img
                class="legacy-contact-map"
                :src="contactMap"
                :alt="t('contact.map_alt')"
            >
        </section>
    </LegacyPageShell>
</template>
