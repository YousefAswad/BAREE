<script setup lang="ts">
const model = defineModel<boolean>({ required: true });
defineProps<{ label: string }>();
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="model"
    :aria-label="label"
    class="relative h-7 w-12 shrink-0 rounded-full transition"
    :class="model ? 'bg-indigo' : 'bg-[#CDC9D6]'"
    @click="model = !model"
  >
    <span class="absolute top-1 h-5 w-5 rounded-full bg-white transition-all" :class="model ? 'right-6' : 'right-1'" />
  </button>
</template>
