<script setup>
import AdminSidebar from './AdminSidebar.vue';
import AdminTourOverlay from './AdminTourOverlay.vue';
import { useAdminShell } from '../composables/useAdminShell';

defineProps({
    activePage: {
        type: String,
        required: true
    }
});

const { csrfToken, isSidebarOpen, isCollapsed } = useAdminShell();
</script>

<template>
    <div class="admin-shell h-dvh overflow-hidden bg-brand-light text-brand-dark flex font-sans antialiased">
        <AdminSidebar
            :is-open="isSidebarOpen"
            :is-collapsed="isCollapsed"
            :active-page="activePage"
            :csrf-token="csrfToken"
            @close="isSidebarOpen = false"
            @toggle-collapse="isCollapsed = !isCollapsed"
        />

        <slot></slot>

        <AdminTourOverlay :csrf-token="csrfToken" />
    </div>
</template>

<style>
.font-sans {
    font-family: 'DM Sans', sans-serif;
}

.admin-shell button:not(:disabled),
.admin-shell a[href],
.admin-shell [role="button"]:not([aria-disabled="true"]),
.admin-shell select:not(:disabled),
.admin-shell input[type="checkbox"]:not(:disabled),
.admin-shell label:has(input:not(:disabled)) {
    cursor: pointer;
}

.admin-shell button:disabled,
.admin-shell [aria-disabled="true"],
.admin-shell select:disabled,
.admin-shell input:disabled {
    cursor: not-allowed;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e5e7eb; /* brand-border equivalent */
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #d4af37; /* brand-gold equivalent */
}
</style>
