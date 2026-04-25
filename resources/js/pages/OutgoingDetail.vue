<script setup>
import { onMounted, ref } from 'vue'
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

async function load() {
    loading.value = true
    error.value = null
    try {
        entry.value = await api.outgoing.show(props.id)
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
            <h1 class="mb-4 text-xl font-semibold text-slate-900">Outgoing Request Details</h1>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="w-44 px-4 py-2.5 text-slate-500">Method</td>
                            <td class="px-4 py-2.5"><MethodBadge :method="entry.method" /></td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">URI</td>
                            <td class="px-4 py-2.5 font-mono text-slate-800 break-all">{{ entry.uri }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Hostname</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.hostname }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Status</td>
                            <td class="px-4 py-2.5">
                                <span v-if="entry.failed" class="inline-flex items-center rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">
                                    FAILED
                                </span>
                                <StatusBadge v-else :status="entry.status" />
                            </td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-500">Duration</td>
                            <td class="px-4 py-2.5 text-slate-800">{{ entry.duration_ms ?? '—' }} ms</td>
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

            <div v-if="entry.error" class="mt-6 overflow-hidden rounded-lg border border-rose-200 bg-rose-50 shadow-sm">
                <div class="border-b border-rose-200 bg-rose-100 px-4 py-2 text-sm font-semibold text-rose-800">Error</div>
                <div class="p-4">
                    <JsonViewer :value="entry.error" />
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex border-b border-slate-200 bg-slate-50 text-sm">
                    <button
                        v-for="tab in ['payload', 'headers']"
                        :key="tab"
                        type="button"
                        class="px-4 py-2.5 font-medium"
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
        </template>
    </div>
</template>
