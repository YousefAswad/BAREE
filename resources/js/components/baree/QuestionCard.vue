<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Option = { id: number; label: Record<string, string> };
const props = defineProps<{
  index: number;
  total: number;
  competency: Record<string, string>;
  stem: Record<string, string>;
  scenario: Record<string, string> | null;
  options: Option[];
  selected: number | null;
  confidence: number | null;
  askConfidence: boolean;
  locale: string;
}>();
const emit = defineEmits<{ select: [optionId: number]; confidence: [value: number] }>();

const letters = [$t('أ'), $t('ب'), $t('ج'), $t('د'), $t('هـ'), $t('و')];
const t = (v: Record<string, string> | null) => (v ? v[props.locale] ?? v.ar ?? Object.values(v)[0] : '');
const confidenceLabels = [$t('مش متأكد'), $t('نوعًا ما'), $t('متأكد'), $t('متأكد جدًا')];
const progress = computed(() => `${props.index + 1} / ${props.total}`);
</script>

<template>
  <article class="rounded-[28px] border border-stone bg-white p-6 shadow-[0_24px_48px_-36px_rgba(30,34,56,.5)] md:p-8">
    <header class="mb-5 flex flex-wrap items-center gap-2">
      <span class="text-sm font-bold text-indigo">{{ $t('سؤال {p0}', { p0: progress }) }}</span>
      <span class="rounded-full bg-mist px-3 py-1 text-xs font-bold text-indigo-deep">{{ t(competency) }}</span>
    </header>

    <p v-if="scenario" class="mb-4 rounded-2xl border border-dashed border-lavender bg-sand p-4 leading-8 text-subtle">
      {{ t(scenario) }}
    </p>
    <h2 class="font-display mb-6 text-2xl font-bold md:text-[28px]">{{ t(stem) }}</h2>

    <div role="radiogroup" :aria-label="t(stem)" class="flex flex-col gap-3">
      <button
        v-for="(option, i) in options"
        :key="option.id"
        type="button"
        role="radio"
        :aria-checked="selected === option.id"
        class="flex min-h-[64px] w-full items-center gap-4 rounded-2xl border px-4 py-3 text-right text-base leading-7 transition"
        :class="selected === option.id ? 'border-2 border-ink bg-white shadow-md' : 'border-stone bg-white hover:border-indigo'"
        @click="emit('select', option.id)"
      >
        <span
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-display font-bold"
          :class="selected === option.id ? 'bg-ink text-apricot' : 'bg-sand text-ink'"
        >{{ letters[i] }}</span>
        {{ t(option.label) }}
      </button>
    </div>

    <fieldset v-if="askConfidence && selected" class="mt-6">
      <legend class="mb-3 text-sm font-bold">{{ $t('قدّيش متأكد من جوابك؟') }}</legend>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="(label, i) in confidenceLabels"
          :key="label"
          type="button"
          :aria-pressed="confidence === i + 1"
          class="min-h-[44px] flex-1 rounded-xl border px-3 text-sm"
          :class="confidence === i + 1 ? 'border-2 border-indigo bg-mist font-bold text-indigo-deep' : 'border-stone bg-white'"
          @click="emit('confidence', i + 1)"
        >{{ label }}</button>
      </div>
    </fieldset>
  </article>
</template>
