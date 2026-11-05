<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { ar, postJson, t } from '@/lib/http';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Entry = { step: string; try: number; submission: string; feedback: string; hint: string | null; scores: Record<string, number>; check: boolean | null; ready: boolean };
const props = defineProps<{
  mission: { key: string; title: Record<string, string>; description: Record<string, string> | null; xp: number; steps: { key: string; title: Record<string, string>; instruction: Record<string, string> }[]; rubric: { criterion: string; weight: number }[]; dataset: Record<string, unknown>[] | null };
  attempt: { id: number; status: string; step: number; score: number | null; transcript: Entry[] };
}>();

const CRITERIA: Record<string, string> = { clarity: $t('وضوح الطلب'), privacy: $t('حماية البيانات'), verification: $t('التحقّق'), improvement: $t('التحسين') };
const step = ref(props.attempt.step);
const status = ref(props.attempt.status);
const score = ref(props.attempt.score);
const transcript = ref<Entry[]>([...props.attempt.transcript]);
const draft = ref('');
const busy = ref(false);
const error = ref('');
const current = computed(() => props.mission.steps[step.value]);
const columns = computed(() => (props.mission.dataset?.[0] ? Object.keys(props.mission.dataset[0]) : []));

async function send() {
  busy.value = true;
  error.value = '';
  try {
    const res = await postJson<{ coach: Entry; check: boolean | null; advanced: boolean; step: number; status: string; score: number | null }>(`/lab/attempts/${props.attempt.id}/submit`, { submission: draft.value });
    transcript.value.push({ ...res.coach, step: current.value.key, try: 0, submission: draft.value, check: res.check });
    step.value = res.step;
    status.value = res.status;
    score.value = res.score;
    if (res.advanced) draft.value = '';
  } catch (e) { error.value = (e as Error).message; }
  finally { busy.value = false; }
}
</script>

<template>
  <StudioShell :title="t(mission.title)" active="lab">
    <div><h1 class="font-display text-3xl font-extrabold">{{ t(mission.title) }}</h1><p class="text-subtle">{{ t(mission.description) }}</p></div>
    <ol class="flex flex-wrap gap-2" :aria-label="$t('الخطوات')">
      <li v-for="(s, i) in mission.steps" :key="s.key" class="rounded-full px-4 py-2 text-sm font-bold" :class="i < step ? 'bg-mist text-indigo-deep' : i === step && status !== 'completed' ? 'bg-ink text-white' : 'bg-white text-subtle'">{{ ar(i + 1) }}. {{ t(s.title) }}</li>
    </ol>

    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
      <div class="flex flex-col gap-4">
        <section v-if="mission.dataset" class="overflow-x-auto rounded-3xl border border-stone bg-white p-4">
          <p class="mb-2 text-xs font-bold text-subtle">{{ $t('بيانات تجريبية') }}</p>
          <table class="w-full min-w-[480px] text-sm"><thead><tr><th v-for="c in columns" :key="c" scope="col" class="border-b border-stone px-2 py-2 text-right">{{ c }}</th></tr></thead>
            <tbody><tr v-for="(row, i) in mission.dataset" :key="i"><td v-for="c in columns" :key="c" class="border-b border-[#F0EDE9] px-2 py-1.5">{{ row[c] }}</td></tr></tbody></table>
        </section>
        <section v-if="status !== 'completed' && current" class="rounded-3xl border border-stone bg-white p-6">
          <h2 class="font-display mb-2 text-lg font-bold">{{ t(current.title) }}</h2>
          <p class="mb-3 leading-8">{{ t(current.instruction) }}</p>
          <form class="flex flex-col gap-3" @submit.prevent="send">
            <label for="sub" class="sr-only">{{ $t('إجابتك') }}</label>
            <textarea id="sub" v-model="draft" required minlength="3" rows="5" class="rounded-xl border border-stone p-3 leading-7" />
            <p v-if="error" role="alert" class="text-sm text-[#9B1C1C]">{{ error }}</p>
            <button type="submit" :disabled="busy" class="min-h-[52px] rounded-2xl bg-ink font-bold text-white">{{ busy ? $t('المدرّب بيقرأ…') : $t('أرسل للمدرّب') }}</button>
          </form>
        </section>
        <section v-else-if="status === 'completed'" class="rounded-3xl bg-ink p-8 text-white">
          <p class="text-sm font-bold text-apricot">{{ $t('خلّصت المهمة') }}</p>
          <p class="font-display mt-2 text-5xl font-extrabold">{{ $t('{p0}٪', { p0: ar(Math.round(score ?? 0)) }) }}</p>
          <p class="mt-2 text-[#CCCFE0]">{{ $t('نتيجتك دخلت جوازك، وحصلت على نقاط الخبرة.') }}</p>
        </section>
      </div>
      <aside class="flex flex-col gap-3" aria-live="polite">
        <h2 class="font-display text-lg font-bold">{{ $t('المدرّب') }}</h2>
        <article v-for="(e, i) in transcript" :key="i" class="rounded-2xl border border-stone bg-white p-4 text-sm leading-7">
          <p v-if="e.check === false" class="mb-1 font-bold text-[#9A4A12]">{{ $t('الإجابة مش دقيقة لسا.') }}</p>
          <p>{{ e.feedback }}</p>
          <p v-if="e.hint" class="mt-1 text-indigo-deep">{{ $t('تلميح: {p0}', { p0: e.hint }) }}</p>
          <div v-if="Object.keys(e.scores ?? {}).length" class="mt-2 flex flex-wrap gap-2"><span v-for="(v, k) in e.scores" :key="k" class="rounded-full bg-mist px-2 py-0.5 text-xs font-bold text-indigo-deep">{{ CRITERIA[k] ?? k }} {{ ar(v) }}</span></div>
        </article>
        <p v-if="!transcript.length" class="text-sm text-subtle">{{ $t('اكتب إجابتك والمدرّب رح يعطيك ملاحظاته هون.') }}</p>
      </aside>
    </div>
  </StudioShell>
</template>
