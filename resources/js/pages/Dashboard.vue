<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api.js'
import { timeAgo, truncate } from '../utils.js'
import MethodBadge from '../components/MethodBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'

const summary = ref(null)
const loading = ref(true)
const error = ref(null)

const buckets = computed(() => summary.value?.incoming.status_buckets ?? { '2xx': 0, '3xx': 0, '4xx': 0, '5xx': 0 })

async function load() {
    loading.value = true
    error.value = null
    try {
        summary.value = await api.dashboard.summary()
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
            <button
                type="button"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="load"
            >
                Refresh
            </button>
        </div>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500">Loading…</div>
        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-rose-700">{{ error }}</div>

        <template v-else-if="summary">
            <p class="mb-4 text-sm text-slate-500">Last {{ summary.window_hours }} hours</p>

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

            <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
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

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h2 class="text-sm font-semibold text-slate-900">Slowest Incoming</h2>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!summary.incoming.slowest.length">
                                <td class="px-4 py-6 text-center text-slate-400" colspan="4">No data.</td>
                            </tr>
                            <tr v-for="row in summary.incoming.slowest" :key="row.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-2.5"><MethodBadge :method="row.method" /></td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-700" :title="row.path">
                                    <RouterLink :to="{ name: 'incoming.show', params: { id: row.id } }" class="hover:text-indigo-700">
                                        {{ truncate(row.path, 40) }}
                                    </RouterLink>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5"><StatusBadge :status="row.status" /></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-slate-500">{{ row.duration_ms }}ms</td>
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
                                <td class="px-4 py-6 text-center text-slate-400" colspan="4">No data.</td>
                            </tr>
                            <tr v-for="row in summary.outgoing.slowest" :key="row.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-2.5"><MethodBadge :method="row.method" /></td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-700" :title="row.uri">
                                    <RouterLink :to="{ name: 'outgoing.show', params: { id: row.id } }" class="hover:text-indigo-700">
                                        {{ truncate(row.hostname || row.uri, 40) }}
                                    </RouterLink>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    <span v-if="row.failed" class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">FAILED</span>
                                    <StatusBadge v-else :status="row.status" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-slate-500">
                                    <span v-if="row.duration_ms !== null">{{ row.duration_ms }}ms</span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
