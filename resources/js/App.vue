<script setup>
import Sidebar from './components/Sidebar.vue'
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 text-slate-900">
        <Sidebar />
        <main class="flex-1 overflow-x-auto px-8 py-8">
            <RouterView />
        </main>
    </div>
</template>
