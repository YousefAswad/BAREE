<script setup lang="ts">
import PassportCard from '@/components/baree/PassportCard.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

const props = defineProps<{
  result: {
    overall: number; competencies: Record<string, number>; level_key: string;
    next: { key: string; gap: number; blockers: { competency: string; required: number; current: number }[] } | null;
    calibration: { overconfident: number; underconfident: number; index: number | null };
  };
  level: { rank: number; key: string; name: Record<string, string> } | null;
  levels: { key: string; name: Record<string, string> }[];
  competencies: { key: string; name: Record<string, string> }[];
}>();

const user = computed(() => (usePage().props.auth as { user: { name: string } }).user);
const nameOf = (key: string) => props.competencies.find((c) => c.key === key)?.name.ar ?? key;
const levelName = (key: string) => props.levels.find((l) => l.key === key)?.name.ar ?? key;
const sorted = computed(() => Object.entries(props.result.competencies).sort((a, b) => b[1] - a[1]));
const nextText = computed(() => {
  const n = props.result.next;
  if (!n) return $t('وصلت لأعلى مستوى');
  if (n.blockers.length) return `لتصير ${levelName(n.key)}: ${nameOf(n.blockers[0].competency)} لازم توصل ${n.blockers[0].required}٪`;
  return `باقي ${Math.ceil(n.gap)} نقاط لتصير ${levelName(n.key)}`;
});
</script>

<template>
  <Head :title="$t('نتيجتك')" />
  <main :dir="dir" class="mx-auto grid max-w-6xl gap-8 px-6 py-12 lg:grid-cols-2">
    <section class="flex flex-col gap-4">
      <p class="text-sm font-bold text-indigo">{{ $t('جوازك جاهز') }}</p>
      <h1 class="font-display text-4xl font-extrabold">{{ $t('أنت {p0}', { p0: props.level?.name.ar }) }}</h1>
      <p class="leading-8 text-subtle">{{ nextText }}</p>
      <PassportCard
        :holder="user.name"
        :level-name="props.level?.name.ar ?? ''"
        :level-rank="props.level?.rank ?? 1"
        :readiness="props.result.overall"
        :next-text="nextText"
      />
      <p v-if="props.result.calibration.index !== null" class="rounded-2xl bg-mist p-4 text-sm leading-7 text-indigo-deep">
        {{ $t('دقّة ثقتك بإجاباتك: {p0}٪.', { p0: Math.round(props.result.calibration.index * 100) }) }}
        <template v-if="props.result.calibration.overconfident">{{ $t('كنت متأكد بـ {p0} إجابات ما كانت الأفضل.', { p0: props.result.calibration.overconfident }) }}</template>
      </p>
    </section>

    <section class="rounded-3xl border border-stone bg-white p-6" aria-labelledby="comp-title">
      <h2 id="comp-title" class="font-display mb-5 text-xl font-bold">{{ $t('كفاءاتك') }}</h2>
      <ul class="flex flex-col gap-4">
        <li v-for="[key, value] in sorted" :key="key" class="flex flex-col gap-1.5">
          <div class="flex justify-between text-sm"><span class="font-bold">{{ nameOf(key) }}</span><span>{{ $t('{p0}٪', { p0: Math.round(value) }) }}</span></div>
          <div class="h-2 overflow-hidden rounded-full bg-stone">
            <div class="h-full rounded-full" :class="value < 60 ? 'bg-coral' : 'bg-indigo'" :style="{ width: `${value}%` }" />
          </div>
        </li>
      </ul>
    </section>
  </main>
</template>
