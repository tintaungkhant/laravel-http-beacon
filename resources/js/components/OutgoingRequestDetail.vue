<script setup>
import { ref } from 'vue'
import { formatDateTimeLocal, formatDateTimeUTC, localTimezoneLabel, timeAgo } from '../utils.js'
import MethodBadge from './MethodBadge.vue'
import StatusBadge from './StatusBadge.vue'
import JsonViewer from './JsonViewer.vue'

defineProps({ entry: { type: Object, required: true } })

const tz = localTimezoneLabel()

const requestTab = ref('payload')
const responseTab = ref('response')
</script>

<template>
    <div>
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
                        <td class="px-4 py-2.5 text-slate-500">Caller</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-800">{{ entry.caller_action || '—' }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2.5 text-slate-500">Happened</td>
                        <td class="px-4 py-2.5 text-slate-800">{{ timeAgo(entry.created_at) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2.5 text-slate-500">Time ({{ tz }})</td>
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
    </div>
</template>
