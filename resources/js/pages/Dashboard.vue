<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api.js'
import { timeAgo, truncate } from '../utils.js'
import MethodBadge from '../components/MethodBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'

const RANGES = [
    { value: 'hour', label: 'Last One Hour' },
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'this_week', label: 'This Week' },
    { value: 'this_month', label: 'This Month' },
    { value: 'week', label: 'Last Week' },
    { value: 'month', label: 'Last Month' },
]

const route = useRoute()
const router = useRouter()

const range = ref(RANGES.find((r) => r.value === route.query.range)?.value ?? 'today')
const summary = ref(null)
const loading = ref(true)
const error = ref(null)

const buckets = computed(() => summary.value?.incoming.status_buckets ?? { '2xx': 0, '3xx': 0, '4xx': 0, '5xx': 0 })
const outgoingBuckets = computed(() => summary.value?.outgoing.status_buckets ?? { '2xx': 0, '3xx': 0, '4xx': 0, '5xx': 0 })
const rangeLabel = computed(() => RANGES.find((r) => r.value === range.value)?.label ?? '')

function startOfDay(d) {
    const out = new Date(d)
    out.setHours(0, 0, 0, 0)
    return out
}

function rangeBounds(name) {
    const now = new Date()
    switch (name) {
        case 'hour': return [new Date(now.getTime() - 3600 * 1000), now]
        case 'today': return [startOfDay(now), now]
        case 'yesterday': {
            const todayStart = startOfDay(now)
            const yesterdayStart = new Date(todayStart.getTime() - 86400 * 1000)
            return [yesterdayStart, new Date(todayStart.getTime() - 1)]
        }
        case 'this_week': {
            // Current calendar week so far, Monday 00:00 → now (ISO 8601)
            const todayStart = startOfDay(now)
            const dayOfWeek = (now.getDay() + 6) % 7
            const thisWeekStart = new Date(todayStart.getTime() - dayOfWeek * 86400 * 1000)
            return [thisWeekStart, now]
        }
        case 'this_month': {
            // Current calendar month so far, 1st 00:00 → now
            const firstOfThisMonth = new Date(now.getFullYear(), now.getMonth(), 1)
            return [firstOfThisMonth, now]
        }
        case 'week': {
            // Previous calendar week, Monday → Sunday (ISO 8601)
            const todayStart = startOfDay(now)
            const dayOfWeek = (now.getDay() + 6) % 7  // Mon=0 .. Sun=6
            const thisWeekStart = new Date(todayStart.getTime() - dayOfWeek * 86400 * 1000)
            const lastWeekStart = new Date(thisWeekStart.getTime() - 7 * 86400 * 1000)
            const lastWeekEnd = new Date(thisWeekStart.getTime() - 1)
            return [lastWeekStart, lastWeekEnd]
        }
        case 'month': {
            // Previous calendar month, 1st 00:00 → last day 23:59:59.999
            const firstOfThisMonth = new Date(now.getFullYear(), now.getMonth(), 1)
            const firstOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
            const lastOfLastMonth = new Date(firstOfThisMonth.getTime() - 1)
            return [firstOfLastMonth, lastOfLastMonth]
        }
        default: return [new Date(now.getTime() - 86400 * 1000), now]
    }
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const [from, to] = rangeBounds(range.value)
        summary.value = await api.dashboard.summary({
            from: from.toISOString(),
            to: to.toISOString(),
        })
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

watch(range, (value) => {
    router.replace({ query: { range: value } })
    load()
})

onMounted(load)
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
            <div class="flex items-center gap-2">
                <select
                    v-model="range"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                    <option v-for="opt in RANGES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="load"
                >
                    Refresh
                </button>
            </div>
        </div>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500">Loading…</div>
        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-rose-700">{{ error }}</div>

        <template v-else-if="summary">
            <p class="mb-4 text-sm text-slate-500">{{ rangeLabel }}</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Incoming</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ summary.incoming.total.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-slate-500">avg {{ summary.incoming.avg_duration_ms || 0 }}ms</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Incoming Failures</p>
                    <p class="mt-2 text-3xl font-semibold" :class="buckets['5xx'] > 0 ? 'text-rose-600' : 'text-slate-900'">
                        {{ buckets['5xx'].toLocaleString() }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">5xx responses</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Outgoing</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ summary.outgoing.total.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-slate-500">avg {{ summary.outgoing.avg_duration_ms || 0 }}ms</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Outgoing Failures</p>
                    <p class="mt-2 text-3xl font-semibold" :class="summary.outgoing.failed > 0 ? 'text-rose-600' : 'text-slate-900'">
                        {{ summary.outgoing.failed.toLocaleString() }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">connection errors</p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Incoming Status Breakdown</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-md bg-emerald-50 px-3 py-3 ring-1 ring-inset ring-emerald-200">
                            <p class="text-xs font-medium text-emerald-700">2xx</p>
                            <p class="mt-1 text-xl font-semibold text-emerald-800">{{ buckets['2xx'].toLocaleString() }}</p>
                        </div>
                        <div class="rounded-md bg-blue-50 px-3 py-3 ring-1 ring-inset ring-blue-200">
                            <p class="text-xs font-medium text-blue-700">3xx</p>
                            <p class="mt-1 text-xl font-semibold text-blue-800">{{ buckets['3xx'].toLocaleString() }}</p>
                        </div>
                        <div class="rounded-md bg-amber-50 px-3 py-3 ring-1 ring-inset ring-amber-200">
                            <p class="text-xs font-medium text-amber-700">4xx</p>
                            <p class="mt-1 text-xl font-semibold text-amber-800">{{ buckets['4xx'].toLocaleString() }}</p>
                        </div>
                        <div class="rounded-md bg-rose-50 px-3 py-3 ring-1 ring-inset ring-rose-200">
                            <p class="text-xs font-medium text-rose-700">5xx</p>
                            <p class="mt-1 text-xl font-semibold text-rose-800">{{ buckets['5xx'].toLocaleString() }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Outgoing Status Breakdown</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-md bg-emerald-50 px-3 py-3 ring-1 ring-inset ring-emerald-200">
                            <p class="text-xs font-medium text-emerald-700">2xx</p>
                            <p class="mt-1 text-xl font-semibold text-emerald-800">{{ outgoingBuckets['2xx'].toLocaleString() }}</p>
                        </div>
                        <div class="rounded-md bg-blue-50 px-3 py-3 ring-1 ring-inset ring-blue-200">
                            <p class="text-xs font-medium text-blue-700">3xx</p>
                            <p class="mt-1 text-xl font-semibold text-blue-800">{{ outgoingBuckets['3xx'].toLocaleString() }}</p>
                        </div>
                        <div class="rounded-md bg-amber-50 px-3 py-3 ring-1 ring-inset ring-amber-200">
                            <p class="text-xs font-medium text-amber-700">4xx</p>
                            <p class="mt-1 text-xl font-semibold text-amber-800">{{ outgoingBuckets['4xx'].toLocaleString() }}</p>
                        </div>
                        <div class="rounded-md bg-rose-50 px-3 py-3 ring-1 ring-inset ring-rose-200">
                            <p class="text-xs font-medium text-rose-700">5xx</p>
                            <p class="mt-1 text-xl font-semibold text-rose-800">{{ outgoingBuckets['5xx'].toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Slowest Incoming</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!summary.incoming.slowest.length">
                                <td class="px-4 py-6 text-center text-slate-400" colspan="6">No data.</td>
                            </tr>
                            <tr
                                v-for="row in summary.incoming.slowest"
                                :key="row.id"
                                class="cursor-pointer hover:bg-slate-50"
                                @click="router.push({ name: 'incoming.show', params: { id: row.id } })"
                            >
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-500">#{{ row.id }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5"><MethodBadge :method="row.method" /></td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-700" :title="row.path">{{ truncate(row.path, 40) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5"><StatusBadge :status="row.status" /></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-slate-500">{{ row.duration_ms }}ms</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-slate-500" :title="row.created_at">{{ timeAgo(row.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Slowest Outgoing</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!summary.outgoing.slowest.length">
                                <td class="px-4 py-6 text-center text-slate-400" colspan="6">No data.</td>
                            </tr>
                            <tr
                                v-for="row in summary.outgoing.slowest"
                                :key="row.id"
                                class="cursor-pointer hover:bg-slate-50"
                                @click="router.push({ name: 'outgoing.show', params: { id: row.id } })"
                            >
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-500">#{{ row.id }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5"><MethodBadge :method="row.method" /></td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-700" :title="row.uri">{{ truncate(row.hostname || row.uri, 40) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span v-if="row.failed" class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">FAILED</span>
                                    <StatusBadge v-else :status="row.status" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-slate-500">
                                    <span v-if="row.duration_ms !== null">{{ row.duration_ms }}ms</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-slate-500" :title="row.created_at">{{ timeAgo(row.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
