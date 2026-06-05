<script setup>
import LegacyPageShell from './LegacyPageShell.vue';

const offerChoices = [
    ['Koe Loe Yuk', 'Foe Yong Hai'],
    ['Tjap Tjoy', 'Garnalen met Gebakken Knoflook'],
    ['Babi Pangang', 'Kipfilet in Zwarte Bonen saus'],
];
</script>

<template>
    <LegacyPageShell>
        <section id="nieuws" class="legacy-offer">
            <p>
                Al jaren is De Gouden Draak een begrip als het gaat om de beste afhaalgerechten in 's-Hertogenbosch.
                Graag trakteren we u op authentieke gerechten uit de Cantonese keuken.
            </p>

            <h2>Speciale Studentenaanbieding</h2>
            <h3>Chinese Rijsttafel (2 personen)</h3>
            <p>Maak een keuze uit 3 van onderstaande keuzegerechten:</p>

            <dl>
                <template v-for="row in offerChoices" :key="row.join('-')">
                    <div>{{ row[0] }}</div>
                    <div>{{ row[1] }}</div>
                </template>
            </dl>

            <p>Met witte rijst. Nasi of bami is mogelijk tegen meerprijs.</p>
            <strong>Prijs: €21,00</strong>
        </section>

        <p class="legacy-contact-link">
            <a href="/contact">Naar Contact</a>
        </p>
    </LegacyPageShell>
</template>
