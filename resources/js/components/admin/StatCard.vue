<script setup lang="ts">
defineProps<{ label: string; value: string | number | null; hint?: string; dark?: boolean }>();
</script>

<template>
  <div class="flex min-h-[132px] flex-col justify-between gap-3 rounded-2xl border p-5 shadow-sm" :class="dark ? 'border-ink bg-ink text-white' : 'border-stone bg-white text-ink'">
    <span class="text-sm font-bold" :class="dark ? 'text-[#D5D8EC]' : 'text-subtle'">{{ label }}</span>
    <span class="font-display text-4xl font-extrabold leading-none tabular-nums" :class="dark ? 'text-apricot' : 'text-ink'">{{ value ?? '—' }}</span>
    <span v-if="hint" class="text-xs leading-5" :class="dark ? 'text-[#D5D8EC]' : 'text-subtle'">{{ hint }}</span>
    <span v-else class="text-xs leading-5">&nbsp;</span>
  </div>
</template>
