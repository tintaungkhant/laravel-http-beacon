<script setup>
import { ref } from 'vue'
import { api } from '../api.js'
import { copyText, formatDateTimeLocal } from '../utils.js'

const props = defineProps({
    requestType: { type: String, required: true },
    requestId: { type: [String, Number], required: true },
})

const EXPIRY_OPTIONS = [
    { value: 'never', label: 'Never' },
    { value: '1h', label: '1 hour' },
    { value: '24h', label: '24 hours' },
    { value: '7d', label: '7 days' },
    { value: '30d', label: '30 days' },
]

const open = ref(false)
const shares = ref([])
const loading = ref(false)
const expiry = ref('never')
const password = ref('')
const creating = ref(false)
const error = ref('')
const copiedId = ref(null)

async function refresh() {
    loading.value = true
    try {
        shares.value = await api.shares.list({
            request_type: props.requestType,
            request_id: props.requestId,
        })
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function openModal() {
    open.value = true
    error.value = ''
    expiry.value = 'never'
    password.value = ''
    await refresh()
}

function close() {
    open.value = false
}

async function create() {
    creating.value = true
    error.value = ''
    try {
        await api.shares.create({
            request_type: props.requestType,
            request_id: props.requestId,
            expiry: expiry.value,
            password: password.value,
        })
        expiry.value = 'never'
        password.value = ''
        await refresh()
    } catch (e) {
        error.value = 'Could not create the link.'
    } finally {
        creating.value = false
    }
}

async function revoke(id) {
    error.value = ''
    try {
        await api.shares.revoke(id)
        await refresh()
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

const badgeClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    expired: 'bg-amber-50 text-amber-700 ring-amber-200',
    revoked: 'bg-slate-100 text-slate-500 ring-slate-200',
}
</script>

<template>
    <span>
        <button
            type="button"
            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            @click="openModal"
        >
            Share
        </button>

        <div v-if="open" class="fixed inset-0 z-30 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-6" @click.self="close">
            <div class="mt-12 w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Share this request</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="close">✕</button>
                </div>

                <div class="space-y-5 px-5 py-4">
                    <p v-if="error" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>

                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <label class="w-20 text-sm text-slate-500">Expires</label>
                            <select v-model="expiry" class="h-[34px] flex-1 rounded-md border border-slate-300 px-2 text-sm">
                                <option v-for="o in EXPIRY_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="w-20 text-sm text-slate-500">Password</label>
                            <input
                                v-model="password"
                                type="text"
                                placeholder="Optional"
                                autocomplete="off"
                                class="h-[34px] flex-1 rounded-md border border-slate-300 px-2 text-sm"
                            />
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="button"
                                :disabled="creating"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                @click="create"
                            >
                                {{ creating ? 'Creating…' : 'Create link' }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Existing links</h3>
                        <div v-if="loading" class="text-sm text-slate-400">Loading…</div>
                        <div v-else-if="!shares.length" class="text-sm italic text-slate-400">No links yet.</div>
                        <ul v-else class="space-y-2">
                            <li v-for="s in shares" :key="s.id" class="rounded-md border border-slate-200 p-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-semibold uppercase ring-1 ring-inset" :class="badgeClass[s.status]">
                                        {{ s.status }}
                                    </span>
                                    <span v-if="s.has_password" class="text-[11px] text-slate-400" title="Password protected">🔒</span>
                                    <input
                                        :value="s.url"
                                        readonly
                                        class="min-w-0 flex-1 truncate rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs text-slate-600"
                                    />
                                    <button
                                        type="button"
                                        class="shrink-0 rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                        @click="copy(s)"
                                    >
                                        {{ copiedId === s.id ? 'Copied' : 'Copy' }}
                                    </button>
                                    <button
                                        v-if="s.status !== 'revoked'"
                                        type="button"
                                        class="shrink-0 rounded border border-rose-300 px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                        @click="revoke(s.id)"
                                    >
                                        Revoke
                                    </button>
                                </div>
                                <div class="mt-1 pl-1 text-[11px] text-slate-400">
                                    Expires: {{ s.expires_at ? formatDateTimeLocal(s.expires_at) : 'Never' }} · {{ s.view_count }} view{{ s.view_count === 1 ? '' : 's' }}
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </span>
</template>
