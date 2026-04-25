<script setup>
import { computed } from 'vue'
import { formatJson } from '../utils.js'

const props = defineProps({ value: { type: null, default: null } })
const text = computed(() => formatJson(props.value))
const isEmpty = computed(() => text.value === '' || text.value === '{}' || text.value === '[]' || text.value === 'null')
</script>

<template>
    <div v-if="isEmpty" class="rounded-md bg-slate-50 px-4 py-3 text-sm italic text-slate-400">empty</div>
    <pre v-else class="overflow-auto rounded-md bg-slate-900 p-4 text-xs leading-relaxed text-slate-100"><code>{{ text }}</code></pre>
</template>
