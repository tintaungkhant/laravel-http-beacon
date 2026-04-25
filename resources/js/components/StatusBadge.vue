<script setup>
import { computed } from 'vue'
import { statusColor } from '../utils.js'

const props = defineProps({
    status: { type: [Number, String, null], default: null },
})

const classes = computed(() => statusColor(props.status))
const label = computed(() => (props.status ?? 'N/A'))
</script>

<template>
    <span
        class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
        :class="classes"
    >
        {{ label }}
    </span>
</template>
