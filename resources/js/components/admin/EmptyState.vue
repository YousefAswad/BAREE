<script setup lang="ts">
defineProps<{ title: string; hint?: string }>();
</script>

<template>
  <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-line bg-sand px-6 py-10 text-center" role="status">
    <span aria-hidden="true" class="flex h-10 w-10 items-center justify-center rounded-full bg-mist text-lg font-extrabold text-indigo-deep">i</span>
    <p class="font-display text-base font-bold text-ink">{{ title }}</p>
    <p v-if="hint" class="max-w-md text-sm leading-7 text-subtle">{{ hint }}</p>
    <slot />
  </div>
</template>
