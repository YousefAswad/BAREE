<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

const props = defineProps<{ user: { name: string; department: string | null; job_title: string | null; profile: Record<string, any> }; isTeacher: boolean }>();
const form = useForm({
  years: props.user.profile.years ?? null, grades: (props.user.profile.grades ?? []) as number[], subjects: props.user.profile.subjects ?? [],
  responsibilities: props.user.profile.responsibilities ?? [], ai_usage: props.user.profile.ai_usage ?? null, locale: 'ar',
});
const chip = (on: boolean) => (on ? 'border-ink bg-ink text-white font-bold' : 'border-stone bg-white');
const toggle = (list: number[], v: number) => (list.includes(v) ? list.splice(list.indexOf(v), 1) : list.push(v));
</script>

<template>
  <Head :title="$t('خلّينا نتعرّف عليك')" />
  <main :dir="dir" class="mx-auto flex max-w-3xl flex-col gap-6 px-6 py-12">
    <div>
      <h1 class="font-display text-3xl font-extrabold">{{ $t('أهلًا {p0}', { p0: user.name }) }}</h1>
      <p class="mt-1 text-subtle">{{ $t('{p0}{p1}. هالمعلومات بتخلّي الأدوات والتدريب مناسبين لشغلك.', { p0: user.job_title ?? '', p1: user.department ? $t('، {p0}', { p0: user.department }) : '' }) }}</p>
    </div>
    <form class="flex flex-col gap-6 rounded-3xl border border-stone bg-white p-6" @submit.prevent="form.post('/onboarding/profile')">
      <fieldset><legend class="mb-2 text-sm font-bold">{{ $t('سنوات الخبرة') }}</legend>
        <div class="flex flex-wrap gap-2"><button v-for="y in ['0-2', '3-7', '8-15', '15+']" :key="y" type="button" :aria-pressed="form.years === y" class="min-h-[40px] rounded-full border px-4 text-sm" :class="chip(form.years === y)" @click="form.years = y">{{ y }}</button></div></fieldset>
      <fieldset v-if="isTeacher"><legend class="mb-2 text-sm font-bold">{{ $t('الصفوف اللي بتدرّسها') }}</legend>
        <div class="flex flex-wrap gap-2"><button v-for="g in 12" :key="g" type="button" :aria-pressed="form.grades.includes(g)" class="min-h-[40px] min-w-[44px] rounded-full border px-3 text-sm" :class="chip(form.grades.includes(g))" @click="toggle(form.grades, g)">{{ g }}</button></div></fieldset>
      <fieldset><legend class="mb-2 text-sm font-bold">{{ $t('كيف بتستخدم الذكاء الاصطناعي حاليًا؟') }}</legend>
        <div class="flex flex-wrap gap-2"><button v-for="u in [['never', $t('ما جرّبت')], ['sometimes', $t('أحيانًا')], ['weekly', $t('كل أسبوع')], ['daily', $t('كل يوم')]]" :key="u[0]" type="button" :aria-pressed="form.ai_usage === u[0]" class="min-h-[40px] rounded-full border px-4 text-sm" :class="chip(form.ai_usage === u[0])" @click="form.ai_usage = u[0]">{{ u[1] }}</button></div></fieldset>
      <button type="submit" :disabled="form.processing" class="min-h-[52px] rounded-2xl bg-ink font-bold text-white">{{ $t('التالي: تقييم الجاهزية') }}</button>
    </form>
  </main>
</template>
