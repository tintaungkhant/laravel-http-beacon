<script setup>
import Navbar from './components/Navbar.vue'
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <Navbar />
        <main class="overflow-x-auto px-8 py-8">
            <RouterView />
        </main>
    </div>
</template>
