<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { ar, t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{
  paths: { key: string; title: Record<string, string>; description: Record<string, string> | null; missions: { key: string; title: Record<string, string>; minutes: number; xp: number; score: number | null }[] }[];
  xp: number;
  badges: { key: string; name: Record<string, string>; color: string }[];
}>();
</script>

<template>
  <StudioShell :title="$t('مختبر AI')" active="lab">
    <section class="flex flex-wrap items-end justify-between gap-4 rounded-[28px] p-8 text-white" style="background: radial-gradient(circle at 90% 0%, rgba(104,120,214,.55), transparent 50%), #1E2238">
      <div><p class="text-sm font-bold text-apricot">{{ $t('مختبر AI') }}</p><h1 class="font-display mt-1 text-3xl font-extrabold">{{ $t('تدرّب بأمان، مع مدرّب') }}</h1>
        <p class="mt-2 max-w-xl leading-7 text-[#CCCFE0]">{{ $t('بيانات تجريبية بأسماء رمزية، ومدرّب بيعطيك ملاحظات بكل خطوة. نتيجتك بتدخل جوازك.') }}</p></div>
      <div class="text-left"><p class="font-display text-4xl font-extrabold text-apricot">{{ ar(xp) }} XP</p>
        <div class="mt-2 flex flex-wrap justify-end gap-2"><span v-for="b in badges" :key="b.key" class="rounded-full px-3 py-1 text-xs font-bold text-ink" :style="{ background: b.color }">{{ t(b.name) }}</span></div></div>
    </section>
    <section v-for="p in paths" :key="p.key" class="rounded-3xl border border-stone bg-white p-6">
      <h2 class="font-display text-xl font-bold">{{ t(p.title) }}</h2>
      <p class="mb-4 text-sm text-subtle">{{ t(p.description) }}</p>
      <ol class="grid gap-3 md:grid-cols-2">
        <li v-for="(m, i) in p.missions" :key="m.key">
          <Link :href="`/lab/missions/${m.key}`" class="flex items-center gap-4 rounded-2xl border border-stone p-4 text-ink no-underline hover:border-indigo">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-display font-extrabold" :class="m.score !== null ? 'bg-mist text-indigo-deep' : 'bg-ink text-apricot'">{{ m.score !== null ? '✓' : ar(i + 1) }}</span>
            <span class="flex-1"><strong class="block">{{ t(m.title) }}</strong><span class="text-xs text-subtle">{{ $t('{p0} دقيقة · {p1} XP', { p0: ar(m.minutes), p1: ar(m.xp) }) }}<template v-if="m.score !== null"> {{ $t('· نتيجتك {p0}٪', { p0: ar(Math.round(m.score)) }) }}</template></span></span>
          </Link>
        </li>
      </ol>
    </section>
  </StudioShell>
</template>
