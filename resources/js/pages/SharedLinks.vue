<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api.js'
import { copyText, formatDateTimeLocal, timeAgo } from '../utils.js'

const shares = ref([])
const loading = ref(true)
const error = ref(null)
const copiedId = ref(null)

async function load() {
    loading.value = true
    error.value = null
    try {
        shares.value = await api.shares.list()
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function revoke(id) {
    try {
        await api.shares.revoke(id)
        await load()
    } catch (e) {
        error.value = 'Could not revoke the link.'
    }
}

async function copy(share) {
    if (await copyText(share.url)) {
        copiedId.value = share.id
        setTimeout(() => { copiedId.value = null }, 1500)
    }
}

function detailPath(share) {
    return `/${share.request_type === 'incoming' ? 'incoming' : 'outgoing'}/${share.request_id}`
}

const badgeClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    expired: 'bg-amber-50 text-amber-700 ring-amber-200',
    revoked: 'bg-slate-100 text-slate-500 ring-slate-200',
}

onMounted(load)
</script>

<template>
    <div>
        <h1 class="mb-4 text-xl font-semibold text-slate-900">Shared Links</h1>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500">Loading…</div>
        <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-rose-700">{{ error }}</div>

        <div v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">Request</th>
                        <th class="px-4 py-2.5">Created</th>
                        <th class="px-4 py-2.5">Expires</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5">Password</th>
                        <th class="px-4 py-2.5 text-right">Views</th>
                        <th class="px-4 py-2.5">Last viewed</th>
                        <th class="px-4 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!shares.length">
                        <td colspan="8" class="px-4 py-10 text-center italic text-slate-400">No shared links.</td>
                    </tr>
                    <tr v-for="s in shares" :key="s.id" class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            <RouterLink :to="detailPath(s)" class="font-medium text-indigo-600 hover:text-indigo-800">
                                {{ s.request_type }} #{{ s.request_id }}
                            </RouterLink>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ timeAgo(s.created_at) }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ s.expires_at ? formatDateTimeLocal(s.expires_at) : 'Never' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-semibold uppercase ring-1 ring-inset" :class="badgeClass[s.status]">
                                {{ s.status }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ s.has_password ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-600">{{ s.view_count }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ s.last_viewed_at ? timeAgo(s.last_viewed_at) : '—' }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                    @click="copy(s)"
                                >
                                    {{ copiedId === s.id ? 'Copied' : 'Copy' }}
                                </button>
                                <button
                                    v-if="s.status !== 'revoked'"
                                    type="button"
                                    class="rounded border border-rose-300 px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                    @click="revoke(s.id)"
                                >
                                    Revoke
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
