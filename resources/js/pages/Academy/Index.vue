<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { ar, t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{
  courses: { key: string; title: Record<string, string>; description: Record<string, string> | null; hours: number; certificate: boolean; source: string; progress: number | null; mandatory: boolean; due_at: string | null }[];
  certificates: { serial: string; issued_at: string; hours: number }[];
  hours: number;
}>();
</script>

<template>
  <StudioShell :title="$t('الأكاديمية')" active="academy">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <h1 class="font-display text-3xl font-extrabold">{{ $t('الأكاديمية') }}</h1>
      <p class="rounded-2xl bg-white px-4 py-2 text-sm"><b>{{ ar(hours) }}</b> {{ $t('ساعة تطوير مهني موثّقة') }}</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Link v-for="c in courses" :key="c.key" :href="`/academy/${c.key}`" class="flex flex-col gap-2 rounded-3xl border border-stone bg-white p-5 text-ink no-underline hover:border-indigo">
        <span class="flex flex-wrap gap-1 text-xs font-bold"><span class="rounded-full bg-sand px-2 py-0.5">{{ c.source === 'baree' ? 'BAREE' : $t('مؤسستك') }}</span><span v-if="c.mandatory" class="rounded-full bg-[#FBE3E3] px-2 py-0.5 text-[#9B1C1C]">{{ $t('إلزامي') }}</span></span>
        <strong class="font-display text-lg">{{ t(c.title) }}</strong>
        <span class="text-sm text-subtle">{{ t(c.description) }}</span>
        <span class="text-xs text-subtle">{{ $t('{p0} ساعة{p1}', { p0: ar(c.hours), p1: c.certificate ? $t('، بشهادة') : '' }) }}</span>
        <span v-if="c.progress !== null" class="h-2 overflow-hidden rounded-full bg-stone" role="progressbar" :aria-valuenow="c.progress" aria-valuemax="100"><span class="block h-full rounded-full bg-indigo" :style="{ width: `${c.progress}%` }" /></span>
      </Link>
    </div>
    <section v-if="certificates.length" class="rounded-3xl border border-stone bg-white p-6">
      <h2 class="font-display mb-3 text-lg font-bold">{{ $t('شهاداتي') }}</h2>
      <ul class="flex flex-col gap-2 text-sm"><li v-for="c in certificates" :key="c.serial" class="flex flex-wrap justify-between gap-2"><span dir="ltr">{{ c.serial }}</span>
        <span class="flex gap-3"><a :href="`/certificates/${c.serial}/download`" class="font-bold text-indigo">PDF</a><a :href="`/certificates/${c.serial}`" class="font-bold text-indigo">{{ $t('رابط التحقّق') }}</a></span></li></ul>
    </section>
  </StudioShell>
</template>
