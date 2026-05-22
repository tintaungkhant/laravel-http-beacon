<script setup>
import { ref } from 'vue'
import { copyText, requestToCurl } from '../utils.js'

const props = defineProps({ entry: { type: Object, required: true } })

const state = ref('idle') // idle | copied | failed
let resetTimer = null

async function copy() {
    const ok = await copyText(requestToCurl(props.entry))
    state.value = ok ? 'copied' : 'failed'

    if (resetTimer) clearTimeout(resetTimer)
    resetTimer = setTimeout(() => { state.value = 'idle' }, 1500)
}

const label = {
    idle: 'Copy as cURL',
    copied: 'Copied!',
    failed: 'Copy failed',
}
</script>

<template>
    <button
        type="button"
        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
        :class="{ 'border-emerald-300 text-emerald-700': state === 'copied', 'border-rose-300 text-rose-700': state === 'failed' }"
        @click="copy"
    >
        {{ label[state] }}
    </button>
</template>
