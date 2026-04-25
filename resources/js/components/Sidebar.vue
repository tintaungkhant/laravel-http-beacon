<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
    <aside class="w-56 shrink-0 border-r border-slate-200 bg-white">
        <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
            <div class="flex size-8 items-center justify-center rounded-md bg-indigo-600 font-bold text-white">B</div>
            <span class="text-base font-semibold text-slate-900">Beacon</span>
        </div>

        <nav class="px-3 py-4">
            <RouterLink
                to="/dashboard"
                active-class="bg-indigo-50 text-indigo-700"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path d="M3 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4Zm0 9a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3Zm9-9a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V4Zm0 8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1v-4Z" />
                </svg>
                Dashboard
            </RouterLink>
            <RouterLink
                to="/incoming"
                active-class="bg-indigo-50 text-indigo-700"
                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path fill-rule="evenodd" d="M2 10a.75.75 0 0 1 .75-.75h11.69l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H2.75A.75.75 0 0 1 2 10Z" clip-rule="evenodd" />
                </svg>
                Incoming
            </RouterLink>
            <RouterLink
                to="/outgoing"
                active-class="bg-indigo-50 text-indigo-700"
                class="mt-1 flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path fill-rule="evenodd" d="M18 10a.75.75 0 0 1-.75.75H5.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 1 1 1.06 1.06L5.56 9.25h11.69A.75.75 0 0 1 18 10Z" clip-rule="evenodd" />
                </svg>
                Outgoing
            </RouterLink>
        </nav>
    </aside>
</template>
