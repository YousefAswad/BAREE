<script setup lang="ts">
import QuestionCard from '@/components/baree/QuestionCard.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

type Question = {
  id: number; type: string; competency: Record<string, string>; stem: Record<string, string>;
  scenario: Record<string, string> | null; options: { id: number; label: Record<string, string> }[];
  answer: { optionId: number | null; confidence: number | null } | null;
};
const props = defineProps<{
  attempt: { id: string; startedAt: string; timeLimit: number | null };
  template: { name: Record<string, string>; confidence: boolean };
  sections: { key: string; questions: Question[] }[];
}>();

const SECTION_LABELS: Record<string, string> = {
  basics: $t('الأساسيات'), prompting: $t('كتابة الطلب'), verification: $t('التحقّق'), safety: $t('الاستخدام المسؤول'),
  creation: $t('صناعة المحتوى'), data: $t('البيانات'), productivity: $t('الإنتاجية'),
};

const flat = computed(() => props.sections.flatMap((s) => s.questions.map((q) => ({ ...q, section: s.key }))));
const answers = reactive<Record<number, { optionId: number | null; confidence: number | null }>>(
  Object.fromEntries(flat.value.map((q) => [q.id, q.answer ?? { optionId: null, confidence: null }])),
);
const index = ref(Math.max(0, flat.value.findIndex((q) => !answers[q.id].optionId)));
const shownAt = ref(Date.now());
const saving = ref(false);
const errors = computed(() => usePage().props.errors as Record<string, string>);

const current = computed(() => flat.value[index.value]);
const answeredCount = computed(() => flat.value.filter((q) => answers[q.id].optionId).length);
const allAnswered = computed(() => answeredCount.value === flat.value.length);

function save(questionId: number) {
  saving.value = true;
  router.post(`/assessments/attempts/${props.attempt.id}/answers`, {
    question_id: questionId,
    option_id: answers[questionId].optionId,
    confidence: answers[questionId].confidence,
    time_ms: Date.now() - shownAt.value,
  }, { preserveScroll: true, preserveState: true, onFinish: () => (saving.value = false) });
}

function select(optionId: number) {
  answers[current.value.id].optionId = optionId;
  save(current.value.id);
}

function setConfidence(value: number) {
  answers[current.value.id].confidence = value;
  save(current.value.id);
}

function go(step: number) {
  index.value = Math.min(flat.value.length - 1, Math.max(0, index.value + step));
  shownAt.value = Date.now();
}

function submit() {
  router.post(`/assessments/attempts/${props.attempt.id}/submit`);
}
</script>

<template>
  <Head :title="$t('تقييم الجاهزية')" />
  <div :dir="dir" class="min-h-screen bg-sand">
    <header class="flex flex-wrap items-center justify-between gap-4 px-6 py-5 md:px-10">
      <span class="font-display text-lg font-bold">{{ props.template.name.ar }}</span>
      <nav :aria-label="$t('أقسام التقييم')" class="flex flex-wrap gap-2">
        <span
          v-for="section in props.sections"
          :key="section.key"
          class="rounded-full px-3 py-1.5 text-xs font-bold"
          :class="section.key === current.section ? 'bg-ink text-white' : 'bg-white text-subtle'"
        >{{ SECTION_LABELS[section.key] ?? section.key }}</span>
      </nav>
      <span class="text-sm text-subtle" aria-live="polite">{{ $t('{p0} من {p1} {p2}', { p0: answeredCount, p1: flat.length, p2: saving ? $t('· بيتحفظ') : '' }) }}</span>
    </header>

    <div class="mx-6 h-1.5 overflow-hidden rounded-full bg-stone md:mx-10" role="progressbar" :aria-valuenow="answeredCount" :aria-valuemax="flat.length">
      <div class="h-full rounded-full bg-indigo transition-all" :style="{ width: `${(answeredCount / flat.length) * 100}%` }" />
    </div>

    <main class="mx-auto flex max-w-3xl flex-col gap-6 px-6 py-10">
      <QuestionCard
        :key="current.id"
        :index="index"
        :total="flat.length"
        :competency="current.competency"
        :stem="current.stem"
        :scenario="current.scenario"
        :options="current.options"
        :selected="answers[current.id].optionId"
        :confidence="answers[current.id].confidence"
        :ask-confidence="props.template.confidence"
        locale="ar"
        @select="select"
        @confidence="setConfidence"
      />

      <p v-if="errors.answer || errors.submit" role="alert" class="rounded-2xl bg-[#FBE3E3] p-4 text-[#9B1C1C]">
        {{ errors.answer || errors.submit }}
      </p>

      <div class="flex justify-between gap-3">
        <button type="button" class="min-h-[52px] rounded-2xl border border-stone bg-white px-6 font-bold disabled:opacity-40" :disabled="index === 0" @click="go(-1)">{{ $t('السابق') }}</button>
        <button
          v-if="index < flat.length - 1"
          type="button"
          class="min-h-[52px] rounded-2xl bg-ink px-8 font-bold text-white disabled:opacity-40"
          :disabled="!answers[current.id].optionId"
          @click="go(1)"
        >{{ $t('التالي') }}</button>
        <button
          v-else
          type="button"
          class="min-h-[52px] rounded-2xl bg-apricot px-8 font-bold text-ink disabled:opacity-40"
          :disabled="!allAnswered"
          @click="submit"
        >{{ $t('شوف نتيجتك') }}</button>
      </div>
    </main>
  </div>
</template>
