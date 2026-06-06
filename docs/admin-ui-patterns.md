# Admin UI Design Patterns

This document defines the standard UI patterns used across the admin panel to ensure consistency with the new "Overzicht" (Overview) design.

## 1. Page Layout
All admin pages should use this base structure:
```vue
<main class="h-dvh overflow-hidden bg-brand-light text-brand-dark flex font-sans antialiased">
    <AdminSidebar ... />
    <section class="flex-1 min-w-0 min-h-0 flex flex-col">
        <!-- Header -->
        <!-- Content Area -->
    </section>
</main>
```

## 2. Header
Standard header with mobile menu button:
```vue
<header class="bg-white border-b border-brand-border px-6 py-4 z-40 flex items-center justify-between flex-shrink-0">
    <div class="flex items-center gap-4">
        <button @click="isSidebarOpen = true" class="lg:hidden p-2 -ml-2 text-stone-600 hover:bg-stone-100 rounded-lg">
            <svg ...><!-- Hamburger Icon --></svg>
        </button>
        <div>
            <p class="text-[9px] uppercase tracking-widest font-black text-brand-gold">Section Label</p>
            <h1 class="text-xl font-black leading-tight">Page Title</h1>
        </div>
    </div>
    <!-- Optional: Header Actions (e.g., "Nieuw Item" button) -->
</header>
```

## 3. Metrics Cards (Optional)
Used for quick stats at the top of the content area:
```vue
<div class="grid grid-cols-2 lg:grid-cols-4 border-b border-brand-border bg-stone-50/50">
    <div class="p-6 border-r border-brand-border">
        <span class="block text-[9px] uppercase font-black text-stone-500 mb-1">Label</span>
        <p class="text-2xl font-black">Value</p>
    </div>
    <!-- ... more columns ... -->
</div>
```

## 4. Content Containers
Use these for the main content areas (sections/aside):
```vue
<div class="bg-white border border-brand-border rounded-2xl shadow-sm overflow-hidden flex flex-col h-full">
    <!-- Header -->
    <div class="p-4 border-b border-stone-100 bg-brand-light/50 flex-shrink-0">
        <h3 class="font-black text-sm text-stone-900 uppercase tracking-tight">Title</h3>
    </div>
    <!-- Scrollable Content -->
    <div class="flex-1 overflow-y-auto custom-scrollbar">
        <!-- ... content ... -->
    </div>
    <!-- Footer/Actions -->
    <div class="p-5 bg-brand-light border-t border-stone-100 flex-shrink-0">
        <!-- ... actions ... -->
    </div>
</div>
```

## 5. Scrollbar Styling
Standardized scrollbar in `<style scoped>`:
```css
.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--color-brand-border);
    border-radius: 10px;
}
```
