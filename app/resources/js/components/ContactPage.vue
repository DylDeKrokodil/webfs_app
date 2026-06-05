<script setup>
import LegacyPageShell from './LegacyPageShell.vue';

const contactMap = '/images/contact-map.png';
</script>

<template>
    <LegacyPageShell>
        <section class="legacy-contact-page" aria-labelledby="contact-title">
            <div class="legacy-contact-card">
                <h2 id="contact-title">
                    De Gouden Draak is eenvoudig te vinden, vlak bij het centrum,
                    5 minuten lopen achter het centraal station.
                </h2>

                <address>
                    Onderwijsboulevard 215, kamer OG112<br>
                    5223 DE 's-Hertogenbosch
                </address>
            </div>

            <img
                class="legacy-contact-map"
                :src="contactMap"
                alt="Kaart naar Onderwijsboulevard 215 in 's-Hertogenbosch"
            >
        </section>
    </LegacyPageShell>
</template>
