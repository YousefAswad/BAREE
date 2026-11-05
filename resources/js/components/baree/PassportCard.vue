<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();
const props = defineProps<{
  holder: string;
  role?: string;
  levelName: string;
  levelRank: number;
  readiness: number;
  nextText?: string;
}>();
const ar = (n: number | string) => String(n).replace(/[0-9]/g, (d) => '٠١٢٣٤٥٦٧٨٩'[Number(d)]);
</script>

<template>
  <section
    class="relative overflow-hidden rounded-3xl p-7 text-white"
    style="background: radial-gradient(circle at 92% 0%, rgba(104,120,214,.5), transparent 55%), #1E2238"
    :aria-label="$t('جواز AI: {p0}، الجاهزية {p1}٪', { p0: props.levelName, p1: props.readiness })"
  >
    <span aria-hidden="true" class="absolute inset-y-0 left-0 w-3" style="background: linear-gradient(180deg,#FFB48A,#A9B4F0 50%,#44529E)" />
    <div class="flex items-center justify-between">
      <span class="text-sm text-[#B8BBD4]"><b dir="ltr" class="font-display tracking-widest text-white">BAREE</b> {{ $t('جواز الذكاء الاصطناعي') }}</span>
      <span class="rounded-full bg-apricot px-3 py-1 text-xs font-extrabold text-ink">{{ $t('المستوى {p0} من ٦', { p0: ar(props.levelRank) }) }}</span>
    </div>
    <p class="font-display mt-5 text-xl font-bold">{{ props.holder }}</p>
    <p v-if="props.role" class="text-sm text-[#B8BBD4]">{{ props.role }}</p>
    <div class="mt-4 flex items-end justify-between">
      <p class="font-display text-3xl font-extrabold">{{ props.levelName }}</p>
      <p class="font-display text-4xl font-extrabold">{{ $t('{p0}٪', { p0: ar(Math.round(props.readiness)) }) }}</p>
    </div>
    <div class="mt-4 grid grid-cols-6 gap-1.5" aria-hidden="true">
      <span v-for="i in 6" :key="i" class="h-1.5 rounded-full" :class="i <= props.levelRank ? 'bg-apricot' : 'bg-white/15'" />
    </div>
    <p v-if="props.nextText" class="mt-3 text-sm text-[#DEE0EE]">{{ props.nextText }}</p>
  </section>
</template>
