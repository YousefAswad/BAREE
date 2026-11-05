<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { ar, postJson, t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Lesson = { id: number; type: string; title: Record<string, string>; minutes: number; body: string | null; video_url: string | null; quiz: { q: string; options: string[] }[]; mission: string | null; done: boolean };
const props = defineProps<{ course: { key: string; title: Record<string, string>; hours: number }; modules: { title: Record<string, string>; lessons: Lesson[] }[]; progress: number }>();

const all = computed(() => props.modules.flatMap((m) => m.lessons));
const current = ref<Lesson | undefined>(all.value.find((l) => !l.done) ?? all.value[0]);
const progress = ref(props.progress);
const answers = reactive<Record<number, number>>({});
const message = ref('');
const certificate = ref<string | null>(null);

async function complete() {
  if (!current.value) return;
  message.value = '';
  try {
    const res = await postJson<{ passed: boolean; score: number | null; progress: number; certificate: string | null }>(`/academy/lessons/${current.value.id}/complete`, { answers: Object.values(answers) });
    progress.value = res.progress;
    if (res.certificate) certificate.value = res.certificate;
    if (res.passed) { current.value.done = true; const next = all.value.find((l) => !l.done); if (next) current.value = next; }
    else message.value = res.score !== null ? `نتيجتك ${ar(Math.round(res.score))}٪. بدك ٧٠٪ لتكمل، راجع الدرس وجرّب مرة ثانية.` : $t('لازم تخلّص المهمة بالمختبر أول.');
  } catch (e) { message.value = (e as Error).message; }
}
</script>

<template>
  <StudioShell :title="t(course.title)" active="academy">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <h1 class="font-display text-3xl font-extrabold">{{ t(course.title) }}</h1>
      <span class="text-sm font-bold">{{ $t('{p0}٪', { p0: ar(progress) }) }}</span>
    </div>
    <p v-if="certificate" role="status" class="rounded-2xl bg-ink p-5 text-white">{{ $t('مبروك! شهادتك جاهزة:') }} <a :href="`/certificates/${certificate}/download`" class="font-bold text-apricot">{{ $t('نزّلها PDF') }}</a></p>
    <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
      <nav :aria-label="$t('الوحدات')" class="flex flex-col gap-4 rounded-3xl border border-stone bg-white p-4">
        <div v-for="(m, i) in modules" :key="i"><p class="mb-2 text-xs font-extrabold text-subtle">{{ t(m.title) }}</p>
          <button v-for="l in m.lessons" :key="l.id" type="button" :aria-current="current?.id === l.id ? 'true' : undefined" class="flex min-h-[44px] w-full items-center gap-2 rounded-xl px-3 text-right text-sm" :class="current?.id === l.id ? 'bg-sand font-bold' : ''" @click="current = l">
            <span class="w-5 text-indigo">{{ l.done ? '✓' : '•' }}</span>{{ t(l.title) }}<span class="mr-auto text-xs text-subtle">{{ $t('{p0}د', { p0: ar(l.minutes) }) }}</span></button></div>
      </nav>
      <section v-if="current" class="flex flex-col gap-4 rounded-3xl border border-stone bg-white p-6">
        <h2 class="font-display text-2xl font-bold">{{ t(current.title) }}</h2>
        <pre v-if="current.body" class="whitespace-pre-wrap font-[inherit] leading-8">{{ current.body }}</pre>
        <div v-if="current.type === 'quiz'" class="flex flex-col gap-4">
          <fieldset v-for="(q, i) in current.quiz" :key="i"><legend class="mb-2 font-bold">{{ ar(i + 1) }}. {{ q.q }}</legend>
            <label v-for="(o, j) in q.options" :key="j" class="mb-1 flex min-h-[44px] items-center gap-2 rounded-xl border border-stone px-3"><input v-model="answers[i]" type="radio" :name="`q${i}`" :value="j" />{{ o }}</label></fieldset>
        </div>
        <Link v-if="current.type === 'lab' && current.mission" :href="`/lab/missions/${current.mission}`" class="min-h-[48px] rounded-2xl bg-mist px-5 py-3 text-center font-bold text-indigo-deep">{{ $t('افتح المهمة بالمختبر') }}</Link>
        <p v-if="message" role="alert" class="rounded-2xl bg-[#FDF0E4] p-3 text-sm text-[#6B330B]">{{ message }}</p>
        <button v-if="!current.done" type="button" class="min-h-[52px] rounded-2xl bg-ink font-bold text-white" @click="complete">{{ current.type === 'quiz' ? $t('سلّم الإجابات') : $t('خلّصت هالدرس') }}</button>
      </section>
    </div>
  </StudioShell>
</template>
