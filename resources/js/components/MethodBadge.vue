<script setup>
import { computed } from 'vue'
import { methodColor } from '../utils.js'

const props = defineProps({ method: { type: String, required: true } })
const classes = computed(() => methodColor(props.method))
</script>

<template>
    <span
        class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-semibold uppercase ring-1 ring-inset"
        :class="classes"
    >
        {{ method }}
    </span>
</template>
