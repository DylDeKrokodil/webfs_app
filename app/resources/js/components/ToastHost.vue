<script setup>
import { computed } from 'vue';
import { toastService } from '../services/toastService';

const icons = {
    success: 'OK',
    error: '!',
    info: 'i',
};

const toasts = computed(() => toastService.toasts.value ?? toastService.toasts);
</script>

<template>
    <Teleport to="body">
        <section class="toast-stack" aria-live="polite" aria-label="Meldingen">
            <TransitionGroup name="toast">
                <article
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="toast-message"
                    :class="`is-${toast.type}`"
                    role="status"
                >
                    <span class="toast-icon" aria-hidden="true">{{ icons[toast.type] ?? icons.info }}</span>
                    <span class="toast-copy">
                        <strong v-if="toast.title">{{ toast.title }}</strong>
                        <span>{{ toast.message }}</span>
                    </span>
                    <button type="button" aria-label="Melding sluiten" @click="toastService.dismiss(toast.id)">
                        x
                    </button>
                </article>
            </TransitionGroup>
        </section>
    </Teleport>
</template>
