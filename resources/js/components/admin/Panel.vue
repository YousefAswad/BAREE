<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string; dark?: boolean }>();
</script>

<template>
  <section class="flex flex-col gap-4 rounded-3xl border p-5 shadow-sm md:p-6" :class="dark ? 'border-ink bg-ink text-white' : 'border-stone bg-white text-ink'">
    <div v-if="title || $slots.actions" class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 v-if="title" class="font-display text-lg font-bold">{{ title }}</h2>
        <p v-if="subtitle" class="mt-1 text-sm leading-6" :class="dark ? 'text-[#D5D8EC]' : 'text-subtle'">{{ subtitle }}</p>
      </div>
      <slot name="actions" />
    </div>
    <slot />
  </section>
</template>
