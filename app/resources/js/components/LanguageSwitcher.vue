<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { loadLocaleMessages } from '../i18n';

const props = defineProps({
    variant: {
        type: String,
        default: 'legacy', // 'legacy' or 'tablet'
    }
});

const { locale } = useI18n();
const isSwitchingLocale = ref(false);

const languages = [
    { code: 'nl', label: 'Nederlands', flag: '🇳🇱' },
    { code: 'en', label: 'English', flag: '🇬🇧' },
    { code: 'de', label: 'Deutsch', flag: '🇩🇪' },
    { code: 'fr', label: 'Français', flag: '🇫🇷' },
    { code: 'zh', label: '中文 (Chinese)', flag: '🇨🇳' },
    { code: 'es', label: 'Español', flag: '🇪🇸' },
    { code: 'it', label: 'Italiano', flag: '🇮🇹' },
    { code: 'ja', label: '日本語 (Japanese)', flag: '🇯🇵' },
    { code: 'ko', label: '한국어 (Korean)', flag: '🇰🇷' },
    { code: 'pt', label: 'Português', flag: '🇵🇹' },
    { code: 'ru', label: 'Русский (Russian)', flag: '🇷🇺' },
    { code: 'ar', label: 'العربية (Arabic)', flag: '🇸🇦' },
    { code: 'tr', label: 'Türkçe', flag: '🇹🇷' },
    { code: 'pl', label: 'Polski', flag: '🇵🇱' },
];

const switchLanguage = async (event) => {
    const lang = event.target.value;
    isSwitchingLocale.value = true;
    
    try {
        await loadLocaleMessages(lang);
        locale.value = lang;
        localStorage.setItem('locale', lang);
    } finally {
        isSwitchingLocale.value = false;
    }
};
</script>

<template>
    <div :class="variant === 'tablet' ? 'tablet-lang-switcher' : 'legacy-lang-switcher'">
        <div v-if="isSwitchingLocale" class="lang-loader"></div>
        <select 
            :value="locale" 
            @change="switchLanguage"
            :disabled="isSwitchingLocale"
            :aria-label="variant === 'tablet' ? 'Selecteer taal' : 'Language'"
            :class="variant === 'tablet' ? 'tablet-lang-select' : 'legacy-lang-select'"
        >
            <option v-for="lang in languages" :key="lang.code" :value="lang.code">
                {{ lang.flag }} {{ lang.label }}
            </option>
        </select>
    </div>
</template>

<style scoped>
/* Common Loader */
.lang-loader {
    width: 14px;
    height: 14px;
    border: 2px solid yellow;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Legacy Variant */
.legacy-lang-switcher {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 0 0 auto;
}

.legacy-lang-select {
    appearance: none;
    border: 1px solid yellow;
    background: #1d1714;
    color: yellow;
    padding: 2px 24px 2px 8px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    border-radius: 4px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='yellow' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 6px center;
}

.legacy-lang-select:hover {
    background-color: #2d2420;
}

.legacy-lang-select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(255, 255, 0, 0.3);
}

/* Tablet Variant */
.tablet-lang-switcher {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tablet-lang-switcher .lang-loader {
    width: 12px;
    height: 12px;
    border-color: #d7c39c;
    border-top-color: transparent;
}

.tablet-lang-select {
    appearance: none;
    border: 1px solid #d7c39c;
    background: #fff;
    color: #4b3327;
    padding: 1px 20px 1px 6px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    border-radius: 6px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%234b3327' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 4px center;
    max-width: 120px;
}

@media (max-width: 480px) {
    .tablet-lang-select {
        max-width: 70px;
        font-size: 10px;
        padding-right: 16px;
    }
}

.tablet-lang-select:focus {
    outline: none;
    border-color: #d7c39c;
}
</style>
