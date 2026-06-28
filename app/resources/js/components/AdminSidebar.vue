<script setup>
const brandLogo = '/images/brand/de-gouden-draak-emblem.png';

defineProps({
    isOpen: {
        type: Boolean,
        default: false
    },
    isCollapsed: {
        type: Boolean,
        default: false
    },
    activePage: {
        type: String,
        required: true
    },
    csrfToken: {
        type: String,
        required: true
    }
});

defineEmits(['close', 'toggle-collapse']);
</script>

<template>
    <!-- Mobile Backdrop -->
    <div
        v-if="isOpen"
        class="fixed inset-0 bg-black/50 z-50 lg:hidden"
        @click="$emit('close')"
    ></div>

    <!-- Sidebar -->
    <aside
        class="fixed inset-y-0 left-0 bg-brand-dark text-white flex flex-col z-50 transform transition-all duration-300 lg:translate-x-0 lg:static lg:h-screen shadow-xl"
        :class="[
            isOpen ? 'translate-x-0' : '-translate-x-full',
            isCollapsed ? 'lg:w-20 w-64' : 'lg:w-56 w-64'
        ]"
    >
        <div class="p-5 border-b border-white/5 flex items-center justify-between overflow-hidden">
            <div class="flex items-center gap-2 min-w-0">
                <img
                    class="brand-lockup-mark flex-shrink-0 w-10 h-10"
                    :src="brandLogo"
                    alt="De Gouden Draak logo"
                >
                <div 
                    class="min-w-0 transition-opacity duration-300"
                    :class="[isCollapsed ? 'lg:opacity-0 lg:absolute lg:-translate-x-10' : 'opacity-100']"
                >
                    <p class="brand-lockup-wordmark is-small text-brand-gold whitespace-nowrap">De Gouden Draak</p>
                    <h2 class="text-[10px] font-bold text-stone-500 uppercase">Admin</h2>
                </div>
            </div>
            <!-- Close button for mobile -->
            <button @click="$emit('close')" class="lg:hidden p-2 text-stone-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-x-hidden">
            <a
                v-for="link in [
                    { id: 'menu', label: 'Menukaart', href: '/admin/menu', icon: 'M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z', path: 'M8 7h6 M8 11h8' },
                    { id: 'kassa', label: 'Kassa', href: '/admin/kassa', icon: 'M2 5h20v14H2z', path: 'M2 10h20' },
                    { id: 'tafels', label: 'Tafels', href: '/admin/tafels', icon: 'M3 3h7v7H3z M14 3h7v7h-7z M14 14h7v7h-7z M3 14h7v7H3z', path: '' },
                    { id: 'overzicht', label: 'Overzicht', href: '/admin/overzicht', icon: 'M12 20v-10 M18 20V4 M6 20v-4', path: '' },
                    { id: 'statistieken', label: 'Statistieken', href: '/admin/statistieken', icon: 'M21.21 15.89A10 10 0 1 1 8 2.83 M22 12A10 10 0 0 0 12 2v10z', path: '' }
                ]"
                :key="link.id"
                :href="link.href"
                :title="link.label"
                :data-tour="`admin-nav-${link.id}`"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs transition-all group"
                :class="[
                    activePage === link.id ? 'bg-white/10 text-white shadow-inner' : 'text-stone-400 hover:bg-white/5 hover:text-white',
                    isCollapsed ? 'lg:justify-center lg:px-0' : ''
                ]"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                    <path v-if="link.icon" :d="link.icon" />
                    <path v-if="link.path" :d="link.path" />
                </svg>
                <span 
                    class="truncate transition-opacity duration-300"
                    :class="[isCollapsed ? 'lg:opacity-0 lg:absolute lg:-translate-x-10' : 'opacity-100']"
                >
                    {{ link.label }}
                </span>
            </a>
        </nav>

        <div class="p-3 border-t border-white/5 space-y-1">
            <button
                @click="$emit('toggle-collapse')"
                class="hidden lg:flex w-full items-center gap-3 px-3 py-2 rounded-lg font-bold text-xs text-stone-400 hover:bg-white/5 hover:text-white transition-all"
                :class="isCollapsed ? 'justify-center px-0' : ''"
                :title="isCollapsed ? 'Uitklappen' : 'Inklappen'"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="transition-transform duration-300"
                    :class="isCollapsed ? 'rotate-180' : ''"
                >
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                <span v-if="!isCollapsed">Inklappen</span>
            </button>

            <form action="/logout" method="POST" class="w-full">
                <input type="hidden" name="_token" :value="csrfToken">
                <button
                    type="submit"
                    class="w-full py-2 rounded-lg font-black text-[9px] uppercase tracking-widest text-stone-500 hover:text-red-400 transition-colors flex items-center gap-3"
                    :class="isCollapsed ? 'lg:justify-center lg:px-0' : 'px-3'"
                    title="Log uit"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 text-stone-500"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                    <span 
                        class="transition-opacity duration-300"
                        :class="[isCollapsed ? 'lg:opacity-0 lg:absolute lg:-translate-x-10' : 'opacity-100']"
                    >
                        Log uit
                    </span>
                </button>
            </form>
        </div>
    </aside>
</template>
