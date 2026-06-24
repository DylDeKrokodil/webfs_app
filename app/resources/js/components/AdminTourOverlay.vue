<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { apiRequest } from '../services/apiService';
import { adminTours } from '../tours/adminTours';

const props = defineProps({
    csrfToken: {
        type: String,
        required: true,
    },
});

const tour = adminTours.firstAdminLogin;
const currentStep = ref(0);
const isVisible = ref(false);
const isLoading = ref(true);
const targetRect = ref(null);
const tooltipStyle = ref({});
const nextButton = ref(null);

const activeStep = computed(() => tour.steps[currentStep.value] ?? tour.steps[0]);
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === tour.steps.length - 1);

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const saveStep = async () => {
    if (!isVisible.value) return;

    try {
        await apiRequest(`/api/admin/tour-progress/${tour.key}`, {
            method: 'PATCH',
            csrfToken: props.csrfToken,
            body: {
                tour_version: tour.version,
                current_step: currentStep.value,
            },
        });
    } catch (error) {
        console.error(error);
    }
};

const positionOverlay = async ({ scrollTarget = true } = {}) => {
    if (!isVisible.value) return;

    if (activeStep.value.path && window.location.pathname !== activeStep.value.path) {
        window.history.pushState(null, '', activeStep.value.path);
        window.dispatchEvent(new PopStateEvent('popstate'));
        await new Promise((resolve) => window.setTimeout(resolve, 80));
    }

    await nextTick();

    const target = document.querySelector(activeStep.value.target);

    if (!target) {
        targetRect.value = null;
        tooltipStyle.value = {
            left: '50%',
            top: '50%',
            transform: 'translate(-50%, -50%)',
            width: `${Math.min(360, window.innerWidth - 32)}px`,
        };
        return;
    }

    if (scrollTarget) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    }

    window.setTimeout(() => {
        const rect = target.getBoundingClientRect();
        const width = Math.min(360, window.innerWidth - 32);
        const left = clamp(rect.left + rect.width / 2 - width / 2, 16, window.innerWidth - width - 16);
        const estimatedHeight = 230;
        const top = rect.bottom + 16 + estimatedHeight < window.innerHeight
            ? rect.bottom + 16
            : clamp(rect.top - estimatedHeight - 16, 16, window.innerHeight - estimatedHeight - 16);

        targetRect.value = {
            left: `${Math.max(8, rect.left - 6)}px`,
            top: `${Math.max(8, rect.top - 6)}px`,
            width: `${rect.width + 12}px`,
            height: `${rect.height + 12}px`,
        };

        tooltipStyle.value = {
            left: `${left}px`,
            top: `${top}px`,
            width: `${width}px`,
        };
    }, scrollTarget ? 220 : 0);
};

const loadProgress = async () => {
    try {
        const payload = await apiRequest('/api/admin/tour-progress', {
            csrfToken: props.csrfToken,
        });
        const progress = (payload.progress ?? []).find((item) => (
            item.tour_key === tour.key && item.tour_version === tour.version
        ));

        if (progress?.completed_at || progress?.dismissed_at) {
            isVisible.value = false;
            return;
        }

        currentStep.value = clamp(progress?.current_step ?? 0, 0, tour.steps.length - 1);
        isVisible.value = true;
        await positionOverlay();
        nextButton.value?.focus();
    } catch (error) {
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

const previousStep = () => {
    if (isFirstStep.value) return;
    currentStep.value -= 1;
};

const nextStep = () => {
    if (isLastStep.value) {
        completeTour();
        return;
    }

    currentStep.value += 1;
};

const completeTour = async () => {
    isVisible.value = false;

    try {
        await apiRequest(`/api/admin/tour-progress/${tour.key}/complete`, {
            method: 'POST',
            csrfToken: props.csrfToken,
            body: {
                tour_version: tour.version,
            },
        });
    } catch (error) {
        console.error(error);
    }
};

const dismissTour = async () => {
    isVisible.value = false;

    try {
        await apiRequest(`/api/admin/tour-progress/${tour.key}/dismiss`, {
            method: 'POST',
            csrfToken: props.csrfToken,
            body: {
                tour_version: tour.version,
            },
        });
    } catch (error) {
        console.error(error);
    }
};

const handleKeydown = (event) => {
    if (!isVisible.value) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        dismissTour();
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        nextStep();
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        previousStep();
    }
};

const handleViewportChange = () => positionOverlay({ scrollTarget: false });

watch(currentStep, async () => {
    await positionOverlay();
    await saveStep();
    nextButton.value?.focus();
});

onMounted(() => {
    loadProgress();
    window.addEventListener('resize', handleViewportChange);
    window.addEventListener('scroll', handleViewportChange, true);
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleViewportChange);
    window.removeEventListener('scroll', handleViewportChange, true);
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div
        v-if="isVisible && !isLoading"
        class="fixed inset-0 z-[1000]"
        aria-live="polite"
    >
        <div v-if="!targetRect" class="absolute inset-0 bg-black/55"></div>

        <div
            v-if="targetRect"
            class="fixed rounded-lg border-2 border-brand-gold bg-transparent shadow-[0_0_0_9999px_rgba(0,0,0,0.55),0_0_0_6px_rgba(212,175,55,0.18)] pointer-events-none transition-all duration-200"
            :style="targetRect"
        ></div>

        <section
            class="fixed rounded-lg bg-white text-stone-900 shadow-2xl border border-brand-border p-5 transition-all duration-200"
            :style="tooltipStyle"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`admin-tour-title-${currentStep}`"
            :aria-describedby="`admin-tour-body-${currentStep}`"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-brand-gold">
                        Stap {{ currentStep + 1 }} van {{ tour.steps.length }}
                    </p>
                    <h2
                        :id="`admin-tour-title-${currentStep}`"
                        class="mt-1 text-base font-black leading-tight"
                    >
                        {{ activeStep.title }}
                    </h2>
                </div>
                <button
                    type="button"
                    class="w-10 h-10 -mr-2 -mt-2 flex items-center justify-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus:ring-2 focus:ring-brand-gold cursor-pointer"
                    aria-label="Rondleiding overslaan"
                    @click="dismissTour"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>

            <p
                :id="`admin-tour-body-${currentStep}`"
                class="mt-3 text-sm font-bold leading-6 text-stone-600"
            >
                {{ activeStep.body }}
            </p>

            <div class="mt-5 flex items-center gap-1.5">
                <span
                    v-for="(_, index) in tour.steps"
                    :key="index"
                    class="h-1.5 rounded-full transition-all"
                    :class="index === currentStep ? 'w-8 bg-brand-gold' : 'w-1.5 bg-stone-200'"
                ></span>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="h-10 px-3 rounded-lg text-[10px] font-black uppercase tracking-widest text-stone-500 hover:text-stone-900 focus:outline-none focus:ring-2 focus:ring-brand-gold disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                    :disabled="isFirstStep"
                    @click="previousStep"
                >
                    Terug
                </button>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="h-10 px-3 rounded-lg text-[10px] font-black uppercase tracking-widest text-stone-500 hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus:ring-2 focus:ring-brand-gold cursor-pointer"
                        @click="dismissTour"
                    >
                        Overslaan
                    </button>
                    <button
                        ref="nextButton"
                        type="button"
                        class="h-10 px-4 rounded-lg bg-brand-dark text-white text-[10px] font-black uppercase tracking-widest hover:bg-stone-800 focus:outline-none focus:ring-2 focus:ring-brand-gold focus:ring-offset-2 cursor-pointer"
                        @click="nextStep"
                    >
                        {{ isLastStep ? 'Afronden' : 'Volgende' }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
