<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'
import { timeAgo, truncate } from '../utils.js'
import MethodBadge from '../components/MethodBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'

const PAGE_SIZE = 50
const METHOD_OPTIONS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD']

const route = useRoute()
const router = useRouter()

const filters = reactive({
    search: route.query.search ?? '',
    method: route.query.method ?? '',
    status: route.query.status ?? '',
    failed: route.query.failed === '1',
    from: route.query.from ?? '',
    to: route.query.to ?? '',
})

const hasActiveFilters = computed(() =>
    filters.search || filters.method || filters.status || filters.failed || filters.from || filters.to,
)

const rows = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const error = ref(null)
const hasMore = ref(false)
const recording = ref(true)
const togglingRecording = ref(false)
const clearing = ref(false)

let filterTimer = null

function activeParams() {
    return {
        search: filters.search || undefined,
        method: filters.method || undefined,
        status: filters.failed ? undefined : (filters.status || undefined),
        failed: filters.failed || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
    }
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const page = await api.outgoing.list(activeParams())
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
        const page = await api.outgoing.list({ ...activeParams(), before_id: lastId })
        rows.value.push(...page)
        hasMore.value = page.length === PAGE_SIZE
    } catch (e) {
        error.value = e.message
    } finally {
        loadingMore.value = false
    }
}

function clearFilters() {
    filters.search = ''
    filters.method = ''
    filters.status = ''
    filters.failed = false
    filters.from = ''
    filters.to = ''
}

async function loadRecording() {
    try {
        const state = await api.recording.status()
        recording.value = state.recording
    } catch {
        // ignore
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
    if (!confirm('Delete all recorded outgoing requests? This cannot be undone.')) return
    clearing.value = true
    try {
        await api.outgoing.clear()
        await load()
    } catch (e) {
        error.value = e.message
    } finally {
        clearing.value = false
    }
}

watch(filters, () => {
    if (filterTimer) clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
        const query = {}
        if (filters.search) query.search = filters.search
        if (filters.method) query.method = filters.method
        if (filters.status && !filters.failed) query.status = filters.status
        if (filters.failed) query.failed = '1'
        if (filters.from) query.from = filters.from
        if (filters.to) query.to = filters.to
        router.replace({ query })
        load()
    }, 250)
})

onMounted(() => {
    load()
    loadRecording()
})
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-900">Outgoing Requests</h1>
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

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search host or URI…"
                class="w-64 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <select
                v-model="filters.method"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >
                <option value="">All methods</option>
                <option v-for="m in METHOD_OPTIONS" :key="m" :value="m">{{ m }}</option>
            </select>
            <select
                v-model="filters.status"
                :disabled="filters.failed"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <option value="">All status</option>
                <option value="2xx">2xx Success</option>
                <option value="3xx">3xx Redirect</option>
                <option value="4xx">4xx Client Error</option>
                <option value="5xx">5xx Server Error</option>
            </select>
            <label class="inline-flex items-center gap-1.5 text-sm text-slate-600">
                From
                <input
                    v-model="filters.from"
                    type="datetime-local"
                    class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
            </label>
            <label class="inline-flex items-center gap-1.5 text-sm text-slate-600">
                To
                <input
                    v-model="filters.to"
                    type="datetime-local"
                    class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
            </label>
            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                <input v-model="filters.failed" type="checkbox" class="size-3.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                Failed only
            </label>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="text-sm font-medium text-slate-500 hover:text-slate-800"
                @click="clearFilters"
            >
                Clear
            </button>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Verb</th>
                        <th class="px-4 py-3">URI</th>
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
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                            {{ hasActiveFilters ? 'No requests match the current filters.' : 'No outgoing requests recorded yet.' }}
                        </td>
                    </tr>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="cursor-pointer hover:bg-slate-50"
                        @click="router.push({ name: 'outgoing.show', params: { id: row.id } })"
                    >
                        <td class="whitespace-nowrap px-4 py-3"><MethodBadge :method="row.method" /></td>
                        <td class="px-4 py-3 font-mono text-slate-700" :title="row.uri">{{ truncate(row.uri, 70) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            <span v-if="row.failed" class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">
                                FAILED
                            </span>
                            <StatusBadge v-else :status="row.status" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-slate-500">
                            <span v-if="row.duration_ms !== null">{{ row.duration_ms }}ms</span>
                            <span v-else>—</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500" :title="row.created_at">{{ timeAgo(row.created_at) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-indigo-600">View</td>
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
