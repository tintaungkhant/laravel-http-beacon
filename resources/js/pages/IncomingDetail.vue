<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'
import { formatDateTimeLocal, formatDateTimeUTC } from '../utils.js'
import MethodBadge from '../components/MethodBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'
import JsonViewer from '../components/JsonViewer.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const router = useRouter()
const entry = ref(null)
const loading = ref(true)
const error = ref(null)

const requestTab = ref('payload')
const responseTab = ref('response')
const sideTab = ref('queries')

const queries = computed(() => entry.value?.queries ?? [])
const models = computed(() => entry.value?.model_touches ?? [])
const jobs = computed(() => entry.value?.job_dispatches ?? [])

async function load() {
    loading.value = true
    error.value = null
    try {
        entry.value = await api.incoming.show(props.id)
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
        <button type="button" class="mb-4 text-sm text-slate-500 hover:text-slate-800" @click="router.back()">← Back</button>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500">Loading…</div>
        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-rose-700">{{ error }}</div>

        <template v-else-if="entry">
            <h1 class="mb-4 text-xl font-semibold text-slate-900">Request Details</h1>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="w-44 px-4 py-2.5 text-slate-500">Method</td>
                            <td class="px-4 py-2.5"><MethodBadge :method="entry.method" /></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Path</td>
                            <td class="px-4 py-2.5 font-mono text-slate-800">{{ entry.path }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Hostname</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.hostname }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Controller Action</td>
                            <td class="px-4 py-2.5 font-mono text-slate-800">{{ entry.controller_action || '—' }}</td>
                        </tr>
                        <tr v-if="entry.middlewares && entry.middlewares.length">
                            <td class="px-4 py-2.5 text-slate-500">Middleware</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.middlewares.join(', ') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Status</td>
                            <td class="px-4 py-2.5"><StatusBadge :status="entry.status" /></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Duration</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.duration_ms ?? '—' }} ms</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Memory</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.memory_mb ?? '—' }} MB</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">IP</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.ip || '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Time</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ formatDateTimeLocal(entry.created_at) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Time (UTC)</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ formatDateTimeUTC(entry.created_at) }}</td>
                        </tr>
                        <tr v-if="entry.request_uuid">
                            <td class="px-4 py-2.5 text-slate-500">UUID</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-slate-800">{{ entry.request_uuid }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex border-b border-slate-200 bg-slate-50 text-sm">
                    <button
                        v-for="tab in ['payload', 'headers']"
                        :key="tab"
                        type="button"
                        class="px-4 py-2.5 font-medium capitalize"
                        :class="requestTab === tab ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-600 hover:text-slate-900'"
                        @click="requestTab = tab"
                    >
                        {{ tab === 'headers' ? 'Request Headers' : 'Payload' }}
                    </button>
                </div>
                <div class="p-4">
                    <JsonViewer :value="requestTab === 'payload' ? entry.payload : entry.request_headers" />
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex border-b border-slate-200 bg-slate-50 text-sm">
                    <button
                        v-for="tab in ['response', 'headers']"
                        :key="tab"
                        type="button"
                        class="px-4 py-2.5 font-medium"
                        :class="responseTab === tab ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-600 hover:text-slate-900'"
                        @click="responseTab = tab"
                    >
                        {{ tab === 'headers' ? 'Response Headers' : 'Response' }}
                    </button>
                </div>
                <div class="p-4">
                    <JsonViewer :value="responseTab === 'response' ? entry.response : entry.response_headers" />
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex border-b border-slate-200 bg-slate-50 text-sm">
                    <button
                        type="button"
                        class="px-4 py-2.5 font-medium"
                        :class="sideTab === 'queries' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-600 hover:text-slate-900'"
                        @click="sideTab = 'queries'"
                    >
                        Queries ({{ queries.length }})
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 font-medium"
                        :class="sideTab === 'models' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-600 hover:text-slate-900'"
                        @click="sideTab = 'models'"
                    >
                        Models ({{ models.length }})
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 font-medium"
                        :class="sideTab === 'jobs' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-600 hover:text-slate-900'"
                        @click="sideTab = 'jobs'"
                    >
                        Jobs ({{ jobs.length }})
                    </button>
                </div>

                <div v-if="sideTab === 'queries'">
                    <div v-if="!queries.length" class="p-6 text-sm italic text-slate-400">No queries recorded.</div>
                    <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Type</th>
                                <th class="px-4 py-2">SQL</th>
                                <th class="px-4 py-2">Caller</th>
                                <th class="px-4 py-2 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="q in queries" :key="q.id">
                                <td class="px-4 py-2 align-top text-xs font-semibold uppercase text-slate-500">{{ q.type }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-800"><div class="max-w-3xl break-all">{{ q.sql_with_bindings || q.sql }}</div></td>
                                <td class="px-4 py-2 align-top font-mono text-xs text-slate-500">{{ q.caller || '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right align-top text-slate-500">{{ q.time_ms }} ms</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="sideTab === 'models'">
                    <div v-if="!models.length" class="p-6 text-sm italic text-slate-400">No model events recorded.</div>
                    <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Action</th>
                                <th class="px-4 py-2">Model</th>
                                <th class="px-4 py-2">Caller</th>
                                <th class="px-4 py-2">Changes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="m in models" :key="m.id">
                                <td class="px-4 py-2 align-top text-xs font-semibold uppercase text-slate-500">{{ m.action }}</td>
                                <td class="px-4 py-2 align-top font-mono text-xs text-slate-800">
                                    {{ m.model_class }}<span v-if="m.model_id" class="text-slate-500">#{{ m.model_id }}</span>
                                </td>
                                <td class="px-4 py-2 align-top font-mono text-xs text-slate-500">{{ m.caller || '—' }}</td>
                                <td class="px-4 py-2 align-top">
                                    <JsonViewer :value="m.changes" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="sideTab === 'jobs'">
                    <div v-if="!jobs.length" class="p-6 text-sm italic text-slate-400">No jobs dispatched.</div>
                    <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Job</th>
                                <th class="px-4 py-2">Caller</th>
                                <th class="px-4 py-2">Connection</th>
                                <th class="px-4 py-2">Queue</th>
                                <th class="px-4 py-2">Payload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="j in jobs" :key="j.id">
                                <td class="px-4 py-2 align-top font-mono text-xs text-slate-800">{{ j.job_class }}</td>
                                <td class="px-4 py-2 align-top font-mono text-xs text-slate-500">{{ j.caller || '—' }}</td>
                                <td class="px-4 py-2 align-top text-slate-700">{{ j.connection || '—' }}</td>
                                <td class="px-4 py-2 align-top text-slate-700">{{ j.queue || '—' }}</td>
                                <td class="px-4 py-2 align-top">
                                    <JsonViewer :value="j.payload" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
