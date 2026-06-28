import { createI18n } from 'vue-i18n';
import nl from './locales/nl.json';

const i18n = createI18n({
    legacy: false,
    locale: localStorage.getItem('locale') || 'nl',
    fallbackLocale: 'nl',
    messages: {
        nl
    }
});

document.documentElement.lang = i18n.global.locale.value;

/**
 * Dynamically load a locale and add it to the i18n instance.
 * If the locale is already loaded, it does nothing.
 */
export async function loadLocaleMessages(lang) {
    if (i18n.global.availableLocales.includes(lang)) {
        return;
    }

    try {
        const response = await fetch(`/api/locales/${lang}`, {
            headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`Locale ${lang} not found`);
        }

        const messages = await response.json();
        i18n.global.setLocaleMessage(lang, messages);
    } catch (e) {
        console.error(`Failed to load messages for locale ${lang}:`, e);
    }
}

// Initial load if needed
if (i18n.global.locale.value !== 'nl') {
    loadLocaleMessages(i18n.global.locale.value);
}

export default i18n;
