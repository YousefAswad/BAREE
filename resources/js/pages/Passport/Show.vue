<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { useLocale } from '@/composables/useLocale';
import { t } from '@/lib/http';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  holder: { name: string; job_title: string | null; department: string | null };
  passport: { readiness: number; level: { rank: number; name: Record<string, string>; color: string } | null; competencies: Record<string, number>; next: { gap?: number; blockers?: string[] } | null; xp: number } | null;
  baseline: Record<string, number> | null;
  competencies: { key: string; name: Record<string, string> }[];
  levels: { rank: number; name: Record<string, string>; min_score: number; color: string }[];
  timeline: { source: string; competency: Record<string, string> | null; score: number; at: string }[];
  badges: { key: string; name: Record<string, string>; color: string; awarded_at: string }[];
  cpdHours: number;
  assessments: { public_id: string; overall_score: number; submitted_at: string }[];
}>();
const { n, date } = useLocale();
const SOURCE: Record<string, string> = { assessment: $t('تقييم'), lab: $t('مختبر'), studio: $t('استوديو'), course: $t('أكاديمية') };

// Radar geometry
const size = 320;
const c = size / 2;
const r = 120;
const axes = computed(() => props.competencies.map((comp, i) => {
  const angle = (Math.PI * 2 * i) / props.competencies.length - Math.PI / 2;
  return { ...comp, x: Math.cos(angle), y: Math.sin(angle) };
}));
const polygon = (values: Record<string, number> | null) =>
  axes.value.map((a) => { const v = Math.max(0, Math.min(100, values?.[a.key] ?? 0)) / 100; return `${c + a.x * r * v},${c + a.y * r * v}`; }).join(' ');
</script>

<template>
  <StudioShell :title="$t('جوازي')" active="passport">
    <section class="grid gap-6 rounded-[30px] bg-ink p-8 text-white lg:grid-cols-[1fr_auto]">
      <div class="flex flex-col gap-2">
        <p class="text-sm font-bold text-apricot">{{ $t('جواز الذكاء الاصطناعي') }}</p>
        <h1 class="font-display text-3xl font-extrabold">{{ holder.name }}</h1>
        <p class="text-[#CCCFE0]">{{ holder.job_title }}<template v-if="holder.department"> · {{ holder.department }}</template></p>
        <div v-if="passport" class="mt-4 flex flex-wrap gap-6">
          <div><p class="font-display text-5xl font-extrabold text-apricot">{{ $t('{p0}٪', { p0: n(Math.round(passport.readiness)) }) }}</p><p class="text-sm text-[#CCCFE0]">{{ $t('الجاهزية') }}</p></div>
          <div><p class="font-display text-2xl font-extrabold">{{ t(passport.level?.name) }}</p><p class="text-sm text-[#CCCFE0]">{{ $t('المستوى') }}</p></div>
          <div><p class="font-display text-2xl font-extrabold">{{ n(passport.xp) }}</p><p class="text-sm text-[#CCCFE0]">{{ $t('نقاط الخبرة') }}</p></div>
          <div><p class="font-display text-2xl font-extrabold">{{ n(cpdHours) }}</p><p class="text-sm text-[#CCCFE0]">{{ $t('ساعات تطوير مهني') }}</p></div>
        </div>
        <p v-else class="mt-4 text-[#CCCFE0]">{{ $t('ما في تقييم لسا. ابدأ التقييم لتاخد جوازك.') }}</p>
      </div>
      <svg v-if="passport" :viewBox="`0 0 ${size} ${size}`" class="h-72 w-72" role="img" :aria-label="$t('رسم الكفاءات: الآن مقارنة بأول تقييم')">
        <circle v-for="ring in [0.25, 0.5, 0.75, 1]" :key="ring" :cx="c" :cy="c" :r="r * ring" fill="none" stroke="rgba(255,255,255,.15)" />
        <line v-for="a in axes" :key="a.key" :x1="c" :y1="c" :x2="c + a.x * r" :y2="c + a.y * r" stroke="rgba(255,255,255,.15)" />
        <polygon v-if="baseline" :points="polygon(baseline)" fill="rgba(169,180,240,.18)" stroke="#A9B4F0" stroke-dasharray="4 4" />
        <polygon :points="polygon(passport.competencies)" fill="rgba(255,180,138,.35)" stroke="#FFB48A" stroke-width="2" />
        <text v-for="a in axes" :key="`l-${a.key}`" :x="c + a.x * (r + 22)" :y="c + a.y * (r + 22)" fill="#DEE0EE" font-size="10" text-anchor="middle" dominant-baseline="middle">{{ t(a.name) }}</text>
      </svg>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-3xl border border-stone bg-white p-6">
        <h2 class="font-display mb-4 text-lg font-bold">{{ $t('سلّم المستويات') }}</h2>
        <ol class="flex flex-col gap-2">
          <li v-for="l in levels" :key="l.rank" class="flex items-center gap-3 rounded-xl p-3" :class="passport?.level?.rank === l.rank ? 'bg-ink text-white' : 'bg-sand'">
            <span class="h-4 w-4 shrink-0 rounded" :style="{ background: l.color }" /><span class="flex-1 font-bold">{{ t(l.name) }}</span><span class="text-xs">{{ n(l.min_score) }}+</span>
          </li>
        </ol>
        <div v-if="passport?.next" class="mt-4 rounded-2xl bg-mist p-4 text-sm text-indigo-deep">
          <p v-if="passport.next.gap">{{ $t('باقي {p0} نقاط للمستوى الجاي.', { p0: n(Math.ceil(passport.next.gap)) }) }}</p>
          <p v-for="b in passport.next.blockers ?? []" :key="b">{{ $t('شرط: {p0}', { p0: b }) }}</p>
        </div>
      </section>
      <section class="rounded-3xl border border-stone bg-white p-6">
        <h2 class="font-display mb-4 text-lg font-bold">{{ $t('الأدلة الأخيرة') }}</h2>
        <ul class="flex flex-col gap-2 text-sm">
          <li v-for="(e, i) in timeline" :key="i" class="flex justify-between gap-2 border-b border-[#F0EDE9] pb-2">
            <span><span class="me-2 rounded-full bg-mist px-2 py-0.5 text-xs font-bold text-indigo-deep">{{ SOURCE[e.source] ?? e.source }}</span>{{ t(e.competency) }}</span>
            <span>{{ $t('{p0}٪ · {p1}', { p0: n(Math.round(e.score)), p1: date(e.at) }) }}</span>
          </li>
          <li v-if="!timeline.length" class="text-subtle">{{ $t('بتظهر هون نتائج المختبر والتقييمات.') }}</li>
        </ul>
      </section>
    </div>

    <section v-if="badges.length" class="rounded-3xl border border-stone bg-white p-6">
      <h2 class="font-display mb-4 text-lg font-bold">{{ $t('الشارات') }}</h2>
      <ul class="flex flex-wrap gap-4">
        <li v-for="b in badges" :key="b.key" class="flex w-28 flex-col items-center gap-2 text-center text-sm">
          <svg viewBox="0 0 60 66" class="h-16 w-16" aria-hidden="true"><polygon points="30,2 57,17 57,49 30,64 3,49 3,17" :fill="b.color" /></svg>
          <span class="font-bold">{{ t(b.name) }}</span><span class="text-xs text-subtle">{{ date(b.awarded_at) }}</span>
        </li>
      </ul>
    </section>
  </StudioShell>
</template>
