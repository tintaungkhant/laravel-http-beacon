<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api.js'
import IncomingRequestDetail from '../components/IncomingRequestDetail.vue'
import CopyCurlButton from '../components/CopyCurlButton.vue'
import ShareButton from '../components/ShareButton.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const router = useRouter()
const entry = ref(null)
const loading = ref(true)
const error = ref(null)

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
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold text-slate-900">Request Details</h1>
                <div class="flex items-center gap-2">
                    <ShareButton request-type="incoming" :request-id="entry.id" />
                    <CopyCurlButton :entry="entry" />
                </div>
            </div>

            <IncomingRequestDetail :entry="entry" />
        </template>
    </div>
</template>
