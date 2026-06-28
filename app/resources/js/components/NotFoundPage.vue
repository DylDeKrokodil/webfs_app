<script setup>
const brandLogo = '/images/brand/de-gouden-draak-emblem.png';
</script>

<template>
    <main class="min-h-screen bg-[#fff8ed] text-stone-950 font-sans antialiased">
        <section class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 flex items-center">
            <div class="w-full max-w-3xl mx-auto">
                <header class="flex items-center justify-between gap-4 mb-6">
                    <a href="/" class="brand-lockup text-stone-800 font-black">
                        <img
                            class="brand-lockup-mark"
                            :src="brandLogo"
                            alt="De Gouden Draak logo"
                        >
                        <span class="brand-lockup-wordmark text-[#8b1e1e]">De Gouden Draak</span>
                    </a>
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-[#a6792f]">404</span>
                </header>

                <section class="bg-white border border-[#eadfca] rounded-lg p-7 sm:p-10 shadow-sm overflow-hidden relative">
                    <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#8b1e1e] via-[#d7b56d] to-[#1f6f55]"></div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-[#a6792f] mb-3">Pagina niet gevonden</p>
                    <h1 class="text-3xl sm:text-5xl font-black leading-tight text-stone-950 mb-4">Deze pagina staat niet op de menukaart.</h1>
                    <p class="text-base text-stone-600 max-w-xl">
                        De link klopt niet meer of de pagina is verplaatst.
                    </p>

                    <div class="mt-8 flex flex-col sm:flex-row gap-3">
                        <a href="/" class="inline-flex justify-center rounded-lg bg-[#8b1e1e] px-5 py-3 font-black text-white shadow-lg">
                            Naar home
                        </a>
                        <a href="/menukaart" class="inline-flex justify-center rounded-lg border border-[#eadfca] px-5 py-3 font-black text-stone-700">
                            Bekijk menukaart
                        </a>
                    </div>
                </section>
            </div>
        </section>
    </main>
</template>
