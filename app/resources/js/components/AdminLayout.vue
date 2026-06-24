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
    <div class="h-dvh overflow-hidden bg-brand-light text-brand-dark flex font-sans antialiased">
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
