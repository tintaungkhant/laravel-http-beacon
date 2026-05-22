<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api.js'
import IncomingRequestDetail from '../components/IncomingRequestDetail.vue'
import OutgoingRequestDetail from '../components/OutgoingRequestDetail.vue'

const props = defineProps({ token: { type: String, required: true } })

const loading = ref(true)
const status = ref('')          // ok | locked | revoked | expired | missing | error
const type = ref('')
const entry = ref(null)

const password = ref('')
const unlocking = ref(false)
const unlockError = ref('')

const MESSAGES = {
    revoked: 'This shared link has been revoked.',
    expired: 'This shared link has expired.',
    missing: 'This request is no longer available.',
    error: 'Unable to load this shared link.',
}

async function load() {
    loading.value = true
    try {
        const data = await api.shared.show(props.token)
        status.value = data.status
        if (data.status === 'ok') {
            type.value = data.type
            entry.value = data.request
        }
    } catch (e) {
        status.value = 'error'
    } finally {
        loading.value = false
    }
}

async function submitPassword() {
    if (!password.value) return
    unlocking.value = true
    unlockError.value = ''
    try {
        await api.shared.unlock(props.token, password.value)
        await load()
    } catch (e) {
        unlockError.value = 'Incorrect password.'
    } finally {
        unlocking.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex items-center gap-3">
            <div class="flex size-8 items-center justify-center rounded-md bg-indigo-600 font-bold text-white">B</div>
            <span class="text-base font-semibold text-slate-900">Beacon — Shared Request</span>
        </div>

        <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500">Loading…</div>

        <div v-else-if="status === 'locked'" class="mx-auto max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-base font-semibold text-slate-900">Password required</h1>
            <p class="mt-1 text-sm text-slate-500">This shared request is password-protected.</p>
            <form class="mt-4 space-y-3" @submit.prevent="submitPassword">
                <input
                    v-model="password"
                    type="password"
                    placeholder="Password"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <p v-if="unlockError" class="text-sm text-rose-600">{{ unlockError }}</p>
                <button
                    type="submit"
                    :disabled="unlocking"
                    class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ unlocking ? 'Unlocking…' : 'Unlock' }}
                </button>
            </form>
        </div>

        <div v-else-if="status === 'ok' && entry">
            <IncomingRequestDetail v-if="type === 'incoming'" :entry="entry" />
            <OutgoingRequestDetail v-else :entry="entry" />
        </div>

        <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-amber-800">
            {{ MESSAGES[status] || MESSAGES.error }}
        </div>
    </div>
</template>
