<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LegacyPageShell from './LegacyPageShell.vue';
import { currencyFormatter as formatter } from '../services/formatters';

const { t } = useI18n();

const props = defineProps({
    token: {
        type: String,
        required: true
    }
});

const order = ref(null);
const qrCode = ref('');
const isLoading = ref(true);
const errorMessage = ref('');
const brandEmblem = '/images/brand/de-gouden-draak-emblem.png';

const loadOrder = async () => {
    try {
        const response = await fetch(`/api/takeaway/orders/${props.token}`, {
            headers: {
                'Accept': 'application/json',
                'X-Locale': localStorage.getItem('locale') || 'nl'
            }
        });
        if (!response.ok) throw new Error('Bestelling niet gevonden');
        
        const data = await response.json();
        order.value = data.order;
        qrCode.value = data.qr_code;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const printPage = () => {
    window.print();
};

onMounted(loadOrder);
</script>

<template>
    <LegacyPageShell>
        <section class="legacy-menu-page confirmation-page">
            <div v-if="isLoading" class="legacy-menu-panel text-center py-12">
                <p class="legacy-menu-state">{{ t('confirmation.loading') }}</p>
            </div>

            <div v-else-if="errorMessage" class="legacy-menu-panel text-center py-12">
                <p class="legacy-menu-state is-error">{{ t('confirmation.not_found') }}</p>
                <div class="mt-8">
                    <a href="/menukaart" class="legacy-menu-download">{{ t('confirmation.back_to_menu') }}</a>
                </div>
            </div>

            <div v-else-if="order" class="legacy-menu-panel">
                <header class="legacy-menu-header">
                    <div>
                        <p>{{ t('confirmation.success_title') }}</p>
                        <h2>{{ t('confirmation.success_message') }}</h2>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase tracking-widest text-stone-500">{{ t('common.order_number') }}</p>
                        <h3 class="text-xl font-black text-brand-red">#{{ order.id }}</h3>
                    </div>
                </header>

                <div class="legacy-confirmation-content">
                    <div class="legacy-confirmation-details">
                        <h3 class="legacy-section-title">{{ t('confirmation.your_dishes') }}</h3>
                        <ul class="legacy-order-lines">
                            <li v-for="line in order.lines" :key="line.number" class="legacy-order-line">
                                <div class="legacy-line-info">
                                    <span class="legacy-line-number">{{ line.number }}</span>
                                    <span class="legacy-line-name">{{ line.name }}</span>
                                </div>
                                <div class="legacy-line-meta">
                                    <span class="legacy-line-qty">x{{ line.quantity }}</span>
                                    <span class="legacy-line-price">{{ formatter.format(line.price * line.quantity) }}</span>
                                </div>
                            </li>
                        </ul>
                        
                        <div class="legacy-order-total">
                            <span class="label">{{ t('confirmation.total_paid') }}</span>
                            <span class="value">{{ formatter.format(order.total) }}</span>
                        </div>
                    </div>

                    <div class="legacy-qr-section">
                        <p class="legacy-qr-label">{{ t('confirmation.scan_label') }}</p>
                        <div class="legacy-qr-wrapper">
                            <img :src="qrCode" :alt="t('confirmation.qr_alt')">
                        </div>
                        <p class="legacy-qr-help">
                            {{ t('confirmation.qr_help') }}
                        </p>
                    </div>
                </div>

                <div class="legacy-confirmation-actions print:hidden">
                    <button @click="printPage" class="legacy-checkout-btn">
                        {{ t('confirmation.print_receipt') }}
                    </button>
                    <a href="/menukaart" class="legacy-secondary-btn">
                        {{ t('confirmation.order_more') }}
                    </a>
                </div>

                <footer class="legacy-confirmation-footer">
                    <div class="legacy-footer-divider"></div>
                    <p class="legacy-location-label">{{ t('confirmation.pickup_location') }}</p>
                    <address class="legacy-location-address">
                        De Gouden Draak • Onderwijsboulevard 215 • 's-Hertogenbosch
                    </address>
                </footer>
            </div>
        </section>
    </LegacyPageShell>
</template>

<style scoped>
.legacy-confirmation-content {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 40px;
    margin-top: 32px;
}

.legacy-section-title {
    font-size: 18px;
    font-weight: 900;
    color: #1d1714;
    border-bottom: 2px solid #e5d7ba;
    padding-bottom: 8px;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.legacy-order-lines {
    list-style: none;
    padding: 0;
    margin: 0;
}

.legacy-order-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #eadbbd;
}

.legacy-line-info {
    display: flex;
    gap: 12px;
    align-items: center;
}

.legacy-line-number {
    width: 32px;
    height: 32px;
    background: #f5ead2;
    border: 1px solid #d7c39c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 13px;
    color: #7f1d1d;
}

.legacy-line-name {
    font-weight: 800;
    font-size: 15px;
    color: #1d1714;
}

.legacy-line-meta {
    display: flex;
    gap: 20px;
    align-items: center;
}

.legacy-line-qty {
    font-weight: 900;
    font-size: 13px;
    color: #594337;
}

.legacy-line-price {
    font-weight: 900;
    font-size: 15px;
    color: #1d1714;
    min-width: 80px;
    text-align: right;
}

.legacy-order-total {
    margin-top: 24px;
    padding: 16px;
    background: #fff7df;
    border: 1px solid #d7b56d;
    display: flex;
    justify-content: flex-end;
    align-items: baseline;
    gap: 16px;
}

.legacy-order-total .label {
    font-weight: 900;
    color: #594337;
    text-transform: uppercase;
    font-size: 12px;
}

.legacy-order-total .value {
    font-size: 28px;
    font-weight: 900;
    color: #7f1d1d;
}

.legacy-qr-section {
    text-align: center;
    background: #ffffff;
    border: 1px solid #eadbbd;
    padding: 24px;
}

.legacy-qr-label {
    font-weight: 900;
    font-size: 11px;
    text-transform: uppercase;
    color: #8a1f17;
    margin-bottom: 16px;
    letter-spacing: 0.1em;
}

.legacy-qr-wrapper {
    background: white;
    padding: 12px;
    border: 2px dashed #d7c39c;
    display: inline-block;
    margin-bottom: 16px;
}

.legacy-qr-wrapper img {
    width: 200px;
    height: 200px;
    display: block;
}

.legacy-qr-help {
    font-size: 12px;
    font-weight: 700;
    color: #5d4a3f;
    line-height: 1.4;
    font-style: italic;
}

.legacy-confirmation-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 32px;
}

.legacy-checkout-btn:hover {
    background: #5f1515;
}

.legacy-checkout-btn:focus {
    outline: 2px solid #7f1d1d;
    outline-offset: 4px;
}

.legacy-secondary-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 52px;
    border: 2px solid #eadbbd;
    background: #f5ead2;
    color: #4b3327;
    font-size: 15px;
    font-weight: 800;
    text-decoration: none;
    text-transform: uppercase;
    transition: background 0.2s;
    cursor: pointer;
}

.legacy-secondary-btn:hover {
    background: #e5d7ba;
}

.legacy-secondary-btn:focus {
    outline: 2px solid #7f1d1d;
    outline-offset: 4px;
}

.legacy-confirmation-footer {
    margin-top: 48px;
    text-align: center;
}

.legacy-footer-divider {
    height: 1px;
    background: #e5d7ba;
    margin-bottom: 24px;
}

.legacy-location-label {
    font-weight: 900;
    font-size: 10px;
    text-transform: uppercase;
    color: #a8a29e;
    margin-bottom: 4px;
    letter-spacing: 0.2em;
}

.legacy-location-address {
    font-style: normal;
    font-weight: 800;
    font-size: 13px;
    color: #594337;
}

@media (max-width: 850px) {
    .legacy-confirmation-content {
        grid-template-columns: 1fr;
    }
    .legacy-confirmation-actions {
        grid-template-columns: 1fr;
    }
}

@media print {
    :deep(header), 
    :deep(.legacy-nav), 
    :deep(.legacy-topbar),
    .print\:hidden {
        display: none !important;
    }

    .legacy-page {
        background: white !important;
        padding: 0 !important;
    }

    .legacy-menu-panel {
        border: none !important;
        padding: 0 !important;
    }

    .legacy-menu-header {
        border-bottom: 2px solid black !important;
    }
}
</style>
