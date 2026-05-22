<script setup>
import { useRoute } from 'vue-router'
import Navbar from './components/Navbar.vue'

const route = useRoute()
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <Navbar v-if="!route.meta.bare" />
        <main class="overflow-x-auto px-8 py-8">
            <RouterView v-slot="{ Component }">
                <KeepAlive :include="['IncomingList', 'OutgoingList']">
                    <component :is="Component" />
                </KeepAlive>
            </RouterView>
        </main>
    </div>
</template>
