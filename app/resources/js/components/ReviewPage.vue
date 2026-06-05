<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const brandLogo = '/images/brand/de-gouden-draak-emblem.png';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const token = window.location.pathname.match(/^\/review\/([A-Za-z0-9]+)$/)?.[1] ?? '';

const invite = ref(null);
const isLoading = ref(true);
const isSubmitting = ref(false);
const isSubmitted = ref(false);
const errorMessage = ref('');
const activeStep = ref(0);

const form = reactive({
    overall_score: 0,
    food_score: 0,
    service_score: 0,
    speed_score: 0,
    favorite_dish: '',
    comment: '',
    contact_permission: false,
});

const scoreLabels = {
    1: 'Kan beter',
    2: 'Matig',
    3: 'Prima',
    4: 'Goed',
    5: 'Top',
};

const steps = [
    {
        key: 'overall_score',
        eyebrow: 'Stap 1 van 5',
        title: 'Hoe was de totale ervaring?',
        copy: 'Kies het gevoel dat het beste past bij uw bezoek.',
    },
    {
        key: 'food_score',
        eyebrow: 'Stap 2 van 5',
        title: 'Hoe smaakte het eten?',
        copy: 'Van de eerste hap tot de laatste sauslepel.',
    },
    {
        key: 'service_score',
        eyebrow: 'Stap 3 van 5',
        title: 'Hoe was de service?',
        copy: 'Denk aan vriendelijkheid, aandacht en afhandeling.',
    },
    {
        key: 'speed_score',
        eyebrow: 'Stap 4 van 5',
        title: 'Hoe snel ging alles?',
        copy: 'Van bestellen tot afrekenen.',
    },
    {
        key: 'details',
        eyebrow: 'Stap 5 van 5',
        title: 'Wat mogen we onthouden?',
        copy: 'Een favoriet gerecht of korte tip helpt het team direct.',
    },
];

const currentStep = computed(() => steps[activeStep.value]);
const isScoreStep = computed(() => currentStep.value.key !== 'details');
const progress = computed(() => ((activeStep.value + 1) / steps.length) * 100);
const canContinue = computed(() => !isScoreStep.value || form[currentStep.value.key] > 0);
const tableLabel = computed(() => invite.value?.table_code ? `Tafel ${invite.value.table_code}` : 'De Gouden Draak');

const selectScore = (score) => {
    form[currentStep.value.key] = score;
};

const nextStep = () => {
    if (!canContinue.value) return;
    activeStep.value = Math.min(activeStep.value + 1, steps.length - 1);
};

const previousStep = () => {
    activeStep.value = Math.max(activeStep.value - 1, 0);
};

const loadInvite = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`/api/reviews/${token}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Reviewlink niet gevonden.');

        invite.value = payload.data;
        isSubmitted.value = Boolean(payload.data?.submitted);
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const submitReview = async () => {
    if (!canContinue.value || isSubmitting.value) return;
    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`/api/reviews/${token}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                overall_score: form.overall_score,
                food_score: form.food_score,
                service_score: form.service_score,
                speed_score: form.speed_score,
                favorite_dish: form.favorite_dish || null,
                comment: form.comment || null,
                contact_permission: form.contact_permission,
            }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationMessage = Object.values(payload.errors ?? {})?.[0]?.[0];
            throw new Error(validationMessage || payload.message || 'Versturen mislukt.');
        }

        isSubmitted.value = true;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(loadInvite);
</script>

<template>
    <main class="min-h-screen bg-[#fff8ed] text-stone-950 font-sans antialiased">
        <section class="min-h-screen px-4 py-6 sm:px-6 lg:px-8 flex items-center">
            <div class="w-full max-w-3xl mx-auto">
                <div class="flex items-center justify-between gap-4 mb-6">
                    <a href="/" class="brand-lockup text-stone-800 font-black">
                        <img
                            class="brand-lockup-mark"
                            :src="brandLogo"
                            alt="De Gouden Draak logo"
                        >
                        <span class="brand-lockup-wordmark text-[#8b1e1e]">De Gouden Draak</span>
                    </a>
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-[#a6792f]">{{ tableLabel }}</span>
                </div>

                <div v-if="isLoading" class="bg-white border border-[#eadfca] rounded-lg p-8 shadow-sm">
                    <div class="h-2 bg-stone-100 rounded-full overflow-hidden mb-6">
                        <div class="h-full w-1/2 bg-[#8b1e1e] animate-pulse"></div>
                    </div>
                    <p class="text-sm font-bold text-stone-500">Reviewformulier laden...</p>
                </div>

                <div v-else-if="errorMessage && !invite" class="bg-white border border-red-200 rounded-lg p-8 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-red-700 mb-3">Link verlopen</p>
                    <h1 class="text-3xl font-black text-stone-950 mb-3">We kunnen dit reviewformulier niet openen.</h1>
                    <p class="text-stone-600">{{ errorMessage }}</p>
                </div>

                <div v-else-if="isSubmitted" class="bg-white border border-[#eadfca] rounded-lg p-7 sm:p-10 shadow-sm overflow-hidden relative">
                    <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#8b1e1e] via-[#d7b56d] to-[#1f6f55]"></div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-[#a6792f] mb-3">Review ontvangen</p>
                    <h1 class="text-3xl sm:text-5xl font-black leading-tight text-stone-950 mb-4">Bedankt voor uw feedback.</h1>
                    <p class="text-base text-stone-600 max-w-xl">
                        Uw review is opgeslagen. Het team gebruikt deze signalen om het eten, de service en de snelheid steeds scherper te krijgen.
                    </p>
                    <div class="mt-8 grid grid-cols-3 gap-2" aria-hidden="true">
                        <div v-for="index in 9" :key="index" class="h-3 rounded-full" :class="index % 3 === 0 ? 'bg-[#8b1e1e]' : index % 3 === 1 ? 'bg-[#d7b56d]' : 'bg-[#1f6f55]'"></div>
                    </div>
                </div>

                <form v-else class="bg-white border border-[#eadfca] rounded-lg shadow-sm overflow-hidden" @submit.prevent="submitReview">
                    <div class="h-2 bg-stone-100">
                        <div class="h-full bg-[#8b1e1e] transition-all duration-300" :style="{ width: `${progress}%` }"></div>
                    </div>

                    <div class="p-6 sm:p-9">
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-[#a6792f] mb-3">{{ currentStep.eyebrow }}</p>
                        <h1 class="text-3xl sm:text-5xl font-black leading-tight text-stone-950">{{ currentStep.title }}</h1>
                        <p class="mt-4 text-base text-stone-600 max-w-xl">{{ currentStep.copy }}</p>

                        <div v-if="isScoreStep" class="mt-8 grid grid-cols-5 gap-2 sm:gap-3" role="radiogroup" :aria-label="currentStep.title">
                            <button
                                v-for="score in [1, 2, 3, 4, 5]"
                                :key="score"
                                type="button"
                                class="aspect-square rounded-lg border text-center transition-all focus:outline-none focus:ring-4 focus:ring-[#d7b56d]/40"
                                :class="form[currentStep.key] === score ? 'border-[#8b1e1e] bg-[#8b1e1e] text-white shadow-lg scale-[1.03]' : 'border-[#eadfca] bg-[#fffaf2] text-stone-700 hover:border-[#d7b56d]'"
                                role="radio"
                                :aria-checked="form[currentStep.key] === score"
                                @click="selectScore(score)"
                            >
                                <span class="block text-2xl sm:text-4xl font-black leading-none">{{ score }}</span>
                                <span class="block mt-1 text-[10px] sm:text-xs font-bold">{{ scoreLabels[score] }}</span>
                            </button>
                        </div>

                        <div v-else class="mt-8 space-y-5">
                            <label class="block">
                                <span class="block text-sm font-black text-stone-900 mb-2">Favoriet gerecht</span>
                                <input
                                    v-model.trim="form.favorite_dish"
                                    maxlength="120"
                                    class="w-full rounded-lg border border-[#eadfca] bg-[#fffaf2] px-4 py-3 text-base font-bold outline-none focus:ring-4 focus:ring-[#d7b56d]/30 focus:border-[#a6792f]"
                                    placeholder="Bijvoorbeeld Babi Pangang"
                                >
                            </label>

                            <label class="block">
                                <span class="block text-sm font-black text-stone-900 mb-2">Opmerking of tip</span>
                                <textarea
                                    v-model.trim="form.comment"
                                    maxlength="1000"
                                    rows="5"
                                    class="w-full resize-none rounded-lg border border-[#eadfca] bg-[#fffaf2] px-4 py-3 text-base outline-none focus:ring-4 focus:ring-[#d7b56d]/30 focus:border-[#a6792f]"
                                    placeholder="Wat ging goed, of wat kan beter?"
                                ></textarea>
                            </label>

                            <label class="flex items-start gap-3 rounded-lg border border-[#eadfca] bg-[#fffaf2] p-4">
                                <input v-model="form.contact_permission" type="checkbox" class="mt-1 w-5 h-5 accent-[#8b1e1e]">
                                <span class="text-sm text-stone-700">
                                    De Gouden Draak mag mijn feedback intern gebruiken om service en gerechten te verbeteren.
                                </span>
                            </label>
                        </div>

                        <p v-if="errorMessage" class="mt-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm font-bold text-red-700">
                            {{ errorMessage }}
                        </p>

                        <div class="mt-8 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                            <button
                                type="button"
                                class="px-5 py-3 rounded-lg border border-[#eadfca] font-black text-stone-700 disabled:opacity-40"
                                :disabled="activeStep === 0 || isSubmitting"
                                @click="previousStep"
                            >
                                Terug
                            </button>

                            <button
                                v-if="activeStep < steps.length - 1"
                                type="button"
                                class="px-6 py-3 rounded-lg bg-[#8b1e1e] text-white font-black shadow-lg disabled:opacity-40"
                                :disabled="!canContinue"
                                @click="nextStep"
                            >
                                Volgende
                            </button>

                            <button
                                v-else
                                type="submit"
                                class="px-6 py-3 rounded-lg bg-[#1f6f55] text-white font-black shadow-lg disabled:opacity-40"
                                :disabled="isSubmitting"
                            >
                                {{ isSubmitting ? 'Versturen...' : 'Review versturen' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>
</template>
