<script setup>
defineProps({
    isOpen: {
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

defineEmits(['close']);
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
        class="fixed inset-y-0 left-0 w-64 bg-brand-dark text-white flex flex-col z-50 transform transition-transform duration-300 lg:translate-x-0 lg:static lg:h-screen lg:w-56 shadow-xl"
        :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="p-5 border-b border-white/5 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-brand-red rounded-lg flex items-center justify-center border border-white/10">
                    <span class="text-white font-black text-sm">G</span>
                </div>
                <div>
                    <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Gouden Draak</p>
                    <h2 class="text-[10px] font-bold text-stone-500 uppercase">Admin</h2>
                </div>
            </div>
            <!-- Close button for mobile -->
            <button @click="$emit('close')" class="lg:hidden p-2 text-stone-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 p-3 space-y-1">
            <a
                href="/admin/menu"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs transition-all"
                :class="activePage === 'menu' ? 'bg-white/10 text-white shadow-inner' : 'text-stone-400 hover:bg-white/5 hover:text-white'"
            >
                <span>Menukaart</span>
            </a>
            <a
                href="/admin/kassa"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs transition-all"
                :class="activePage === 'kassa' ? 'bg-white/10 text-white shadow-inner' : 'text-stone-400 hover:bg-white/5 hover:text-white'"
            >
                <span>Kassa</span>
            </a>
            <a
                href="/admin/tafels"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs transition-all"
                :class="activePage === 'tafels' ? 'bg-white/10 text-white shadow-inner' : 'text-stone-400 hover:bg-white/5 hover:text-white'"
            >
                <span>Tafels</span>
            </a>
            <a
                href="/admin/overzicht"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-bold text-xs transition-all"
                :class="activePage === 'overzicht' ? 'bg-white/10 text-white shadow-inner' : 'text-stone-400 hover:bg-white/5 hover:text-white'"
            >
                <span>Overzicht</span>
            </a>
        </nav>

        <form action="/logout" method="POST" class="p-3 border-t border-white/5">
            <input type="hidden" name="_token" :value="csrfToken">
            <button type="submit" class="w-full py-2 rounded-lg font-black text-[9px] uppercase tracking-widest text-stone-500 hover:text-red-400 transition-colors">
                Log uit
            </button>
        </form>
    </aside>
</template>
