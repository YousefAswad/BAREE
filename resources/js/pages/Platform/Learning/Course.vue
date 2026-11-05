<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { t } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ course: any; modules: { id: number; title: Record<string, string>; lessons: any[] }[]; missions: { id: number; key: string; title: Record<string, string> }[] }>();
const module = useForm({ title: '' });
const lesson = useForm({ module_id: props.modules[0]?.id ?? null, type: 'reading', title: '', body: '', video_url: '', mission_id: null as number | null, minutes: 5, quiz: [] as { q: string; options: string[]; answer: number }[] });
const quizJson = ref('[{"q":"","options":["",""],"answer":0}]');
const quizError = ref('');
function addLesson() {
  if (lesson.type === 'quiz') {
    try { lesson.quiz = JSON.parse(quizJson.value); quizError.value = ''; } catch { quizError.value = $t('صيغة JSON غير صحيحة'); return; }
  }
  lesson.post(`/platform/authoring/courses/${props.course.id}/lessons`, { preserveScroll: true, onSuccess: () => lesson.reset('title', 'body', 'video_url') });
}
const TYPES: Record<string, string> = { reading: $t('قراءة'), video: $t('فيديو'), quiz: $t('اختبار'), lab: $t('مهمة بالمختبر') };
</script>

<template>
  <AdminShell portal="platform" active="learning" :title="t(course.title)" :subtitle="$t('{p0} ساعة · {p1}', { p0: course.hours, p1: course.status })">
    <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
      <Panel :title="$t('الوحدات والدروس')">
        <div v-for="m in modules" :key="m.id"><p class="mb-1 font-bold">{{ t(m.title) }}</p>
          <ul class="mb-3 flex flex-col gap-1 text-sm"><li v-for="l in m.lessons" :key="l.id" class="rounded-lg bg-sand px-3 py-1.5">{{ TYPES[l.type] }} · {{ t(l.title) }}</li></ul></div>
        <form class="flex gap-2" @submit.prevent="module.post(`/platform/authoring/courses/${course.id}/modules`, { preserveScroll: true, onSuccess: () => module.reset() })">
          <input v-model="module.title" required :placeholder="$t('وحدة جديدة')" :aria-label="$t('عنوان الوحدة')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white">{{ $t('أضف') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('درس جديد')">
        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="addLesson">
          <select v-model="lesson.module_id" required :aria-label="$t('الوحدة')" class="min-h-[44px] rounded-xl border border-stone px-3"><option v-for="m in modules" :key="m.id" :value="m.id">{{ t(m.title) }}</option></select>
          <select v-model="lesson.type" :aria-label="$t('النوع')" class="min-h-[44px] rounded-xl border border-stone px-3"><option v-for="(l, k) in TYPES" :key="k" :value="k">{{ l }}</option></select>
          <input v-model="lesson.title" required :placeholder="$t('عنوان الدرس')" :aria-label="$t('عنوان الدرس')" class="min-h-[44px] rounded-xl border border-stone px-3 md:col-span-2" />
          <textarea v-if="lesson.type === 'reading'" v-model="lesson.body" rows="8" placeholder="Markdown" :aria-label="$t('المحتوى')" class="rounded-xl border border-stone p-3 md:col-span-2" />
          <input v-if="lesson.type === 'video'" v-model="lesson.video_url" dir="ltr" placeholder="https://" :aria-label="$t('رابط الفيديو')" class="min-h-[44px] rounded-xl border border-stone px-3 md:col-span-2" />
          <select v-if="lesson.type === 'lab'" v-model="lesson.mission_id" :aria-label="$t('المهمة')" class="min-h-[44px] rounded-xl border border-stone px-3 md:col-span-2"><option v-for="ms in missions" :key="ms.id" :value="ms.id">{{ t(ms.title) }}</option></select>
          <textarea v-if="lesson.type === 'quiz'" v-model="quizJson" rows="6" dir="ltr" :aria-label="$t('الأسئلة (JSON)')" class="rounded-xl border border-stone p-3 font-mono text-xs md:col-span-2" />
          <p v-if="quizError" role="alert" class="text-sm text-[#9B1C1C]">{{ quizError }}</p>
          <label class="flex items-center gap-2 text-sm">{{ $t('المدة بالدقائق') }}<input v-model.number="lesson.minutes" type="number" min="1" class="min-h-[40px] w-24 rounded-xl border border-stone px-2" /></label>
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('أضف الدرس') }}</button>
        </form>
      </Panel>
    </div>
  </AdminShell>
</template>
