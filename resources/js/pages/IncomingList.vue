<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api.js'
import { timeAgo, truncate } from '../utils.js'
import MethodBadge from '../components/MethodBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'

const PAGE_SIZE = 50

const rows = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const error = ref(null)
const hasMore = ref(false)
const recording = ref(true)
const togglingRecording = ref(false)
const clearing = ref(false)

async function load() {
    loading.value = true
    error.value = null
    try {
        const page = await api.incoming.list()
        rows.value = page
        hasMore.value = page.length === PAGE_SIZE
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (loadingMore.value || !rows.value.length) return
    loadingMore.value = true
    error.value = null
    try {
        const lastId = rows.value[rows.value.length - 1].id
        const page = await api.incoming.list(lastId)
        rows.value.push(...page)
        hasMore.value = page.length === PAGE_SIZE
    } catch (e) {
        error.value = e.message
    } finally {
        loadingMore.value = false
    }
}

async function loadRecording() {
    try {
        const state = await api.recording.status()
        recording.value = state.recording
    } catch {
        // ignore — leave default
    }
}

async function toggleRecording() {
    togglingRecording.value = true
    try {
        const state = recording.value
            ? await api.recording.pause()
            : await api.recording.resume()
        recording.value = state.recording
    } catch (e) {
        error.value = e.message
    } finally {
        togglingRecording.value = false
    }
}

async function clearAll() {
    if (!confirm('Delete all recorded incoming requests? This cannot be undone.')) return
    clearing.value = true
    try {
        await api.incoming.clear()
        await load()
    } catch (e) {
        error.value = e.message
    } finally {
        clearing.value = false
    }
}

onMounted(() => {
    load()
    loadRecording()
})
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-900">Incoming Requests</h1>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="togglingRecording"
                    :title="recording ? 'Click to pause recording' : 'Click to resume recording'"
                    @click="toggleRecording"
                >
                    <span class="size-2 rounded-full" :class="recording ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                    {{ recording ? 'Recording' : 'Paused' }}
                </button>

                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="load"
                >
                    Refresh
                </button>

                <button
                    type="button"
                    class="rounded-md border border-rose-300 bg-white px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="clearing || rows.length === 0"
                    @click="clearAll"
                >
                    {{ clearing ? 'Deleting…' : 'Delete all' }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Verb</th>
                        <th class="px-4 py-3">Path</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Duration</th>
                        <th class="px-4 py-3">Happened</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">Loading…</td>
                    </tr>
                    <tr v-else-if="error">
                        <td colspan="6" class="px-4 py-10 text-center text-rose-600">{{ error }}</td>
                    </tr>
                    <tr v-else-if="rows.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">No requests recorded yet.</td>
                    </tr>
                    <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3"><MethodBadge :method="row.method" /></td>
                        <td class="px-4 py-3 font-mono text-slate-700" :title="row.path">{{ truncate(row.path, 60) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center"><StatusBadge :status="row.status" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-slate-500">
                            <span v-if="row.duration_ms !== null">{{ row.duration_ms }}ms</span>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500" :title="row.created_at">{{ timeAgo(row.created_at) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <RouterLink
                                :to="{ name: 'incoming.show', params: { id: row.id } }"
                                class="text-indigo-600 hover:text-indigo-800"
                            >
                                View
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!loading && hasMore" class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-center">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-4 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
                    {{ loadingMore ? 'Loading…' : 'Load more' }}
                </button>
            </div>
        </div>
    </div>
</template>
