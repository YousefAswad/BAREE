<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { useLocale } from '@/composables/useLocale';
import { t } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
  courses: { id: number; key: string; title: Record<string, string> }[]; missions: { id: number; key: string; title: Record<string, string> }[];
  departments: { id: number; name: string }[]; segments: { id: number; name: string }[];
  compliance: { course: Record<string, string>; total: number; completed: number; overdue: { id: string; name: string; progress: number; due_at: string }[] }[];
  missionAssignments: { id: number; mission: Record<string, string>; total: number; completed: number; due_at: string | null; mandatory: boolean }[];
}>();
const { t: $t } = useI18n();
const { n, date } = useLocale();
const form = useForm({ type: 'course', id: null as number | null, departments: [] as number[], segments: [] as number[], mandatory: true, due_at: '' });
</script>

<template>
  <AdminShell portal="institution" active="assignments" :title="$t('التعيينات الإلزامية')" :subtitle="$t('كورسات ومهمات بمواعيد. التذكيرات بتنبعت تلقائيًا قبل الموعد بثلاث أيام، وبعده أسبوعيًا.')">
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <Panel :title="$t('تعيين جديد')">
        <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="form.post('/admin/assignments', { preserveScroll: true })">
          <div class="flex gap-2 sm:col-span-2"><label v-for="tp in ['course', 'mission']" :key="tp" class="flex min-h-[40px] items-center gap-2 rounded-xl border border-stone px-3 text-sm"><input v-model="form.type" type="radio" :value="tp" />{{ $t(tp === 'course' ? $t('كورس') : $t('مهمة بالمختبر')) }}</label></div>
          <label class="flex flex-col gap-1 text-sm font-bold sm:col-span-2">{{ $t('المحتوى') }}<select v-model="form.id" required class="min-h-[44px] rounded-xl border border-stone px-3">
            <option v-for="item in (form.type === 'course' ? courses : missions)" :key="item.id" :value="item.id">{{ t(item.title) }}</option></select></label>
          <fieldset class="sm:col-span-2"><legend class="mb-1 text-sm font-bold">{{ $t('الأقسام (فارغ = الكل)') }}</legend><label v-for="d in departments" :key="d.id" class="me-3 inline-flex min-h-[36px] items-center gap-1 text-sm"><input v-model="form.departments" type="checkbox" :value="d.id" />{{ d.name }}</label></fieldset>
          <label class="flex items-center gap-2 text-sm font-bold"><input v-model="form.mandatory" type="checkbox" />{{ $t('إلزامي') }}</label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('آخر موعد') }}<input v-model="form.due_at" type="date" class="min-h-[44px] rounded-xl border border-stone px-3" /></label>
          <button type="submit" class="min-h-[48px] rounded-xl bg-ink font-bold text-white sm:col-span-2">{{ $t('عيّن') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('المهمات المعيّنة')">
        <div v-for="m in missionAssignments" :key="m.id" class="flex justify-between border-b border-[#F0EDE9] py-2 text-sm"><span>{{ t(m.mission) }}</span><span>{{ n(m.completed) }} / {{ n(m.total) }}</span></div>
      </Panel>
    </div>
    <Panel v-for="(c, i) in compliance" :key="i" :title="t(c.course)" class="mt-6">
      <p class="text-sm">{{ $t('أكمل {done} من {total}', { done: n(c.completed), total: n(c.total) }) }}</p>
      <div v-if="c.overdue.length"><p class="mb-1 text-sm font-bold text-[#9B1C1C]">{{ $t('متأخرين') }}</p>
        <ul class="flex flex-col gap-1 text-sm"><li v-for="o in c.overdue" :key="o.id" class="flex justify-between rounded-lg bg-sand px-3 py-1.5"><span>{{ o.name }}</span><span>{{ $t('{p0}٪ · {p1}', { p0: n(o.progress), p1: date(o.due_at) }) }}</span></li></ul></div>
    </Panel>
  </AdminShell>
</template>
