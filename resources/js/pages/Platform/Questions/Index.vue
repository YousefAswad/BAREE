<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { t } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  questions: { data: any[] };
  competencies: { id: number; key: string; name: Record<string, string> }[];
  templates: { id: number; key: string; name: Record<string, string>; items_count: number }[];
  filters: { competency?: string };
}>();
const form = useForm({
  competency_id: props.competencies[0]?.id ?? null, type: 'scenario', difficulty: 2, status: 'draft', template_id: null as number | null,
  stem: { ar: '' }, scenario: { ar: '' },
  options: [{ label: '', score: 3, feedback: '' }, { label: '', score: 2, feedback: '' }, { label: '', score: 1, feedback: '' }, { label: '', score: 0, feedback: '' }],
});
</script>

<template>
  <AdminShell portal="platform" active="questions" :title="$t('بنك الأسئلة')" :subtitle="$t('كل سؤال إله جواب واحد أفضل (٣) وخيار ضعيف واحد على الأقل. الأسئلة المتقاعدة بتضل محفوظة لنتائج التقييمات القديمة.')">
    <Panel :title="$t('سؤال جديد')">
      <form class="grid gap-3 md:grid-cols-2" @submit.prevent="form.post('/platform/questions', { preserveScroll: true })">
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الكفاءة') }}<select v-model="form.competency_id" class="min-h-[44px] rounded-xl border border-stone px-3"><option v-for="c in competencies" :key="c.id" :value="c.id">{{ t(c.name) }}</option></select></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('القالب (اختياري)') }}<select v-model="form.template_id" class="min-h-[44px] rounded-xl border border-stone px-3"><option :value="null">{{ $t('بدون') }}</option><option v-for="tp in templates" :key="tp.id" :value="tp.id">{{ t(tp.name) }} ({{ tp.items_count }})</option></select></label>
        <label class="flex flex-col gap-1 text-sm font-bold md:col-span-2">{{ $t('الموقف') }}<textarea v-model="form.scenario.ar" rows="3" class="rounded-xl border border-stone p-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold md:col-span-2">{{ $t('السؤال') }}<input v-model="form.stem.ar" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <div v-for="(o, i) in form.options" :key="i" class="grid grid-cols-[1fr_auto] gap-2 md:col-span-2">
          <input v-model="o.label" required :aria-label="$t('الخيار {p0}', { p0: i + 1 })" :placeholder="$t('نص الخيار')" class="min-h-[44px] rounded-xl border border-stone px-3" />
          <select v-model.number="o.score" :aria-label="$t('درجة الخيار {p0}', { p0: i + 1 })" class="min-h-[44px] rounded-xl border border-stone px-2"><option v-for="s in [3, 2, 1, 0]" :key="s" :value="s">{{ s }}</option></select>
        </div>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الصعوبة') }}<input v-model.number="form.difficulty" type="number" min="1" max="5" class="min-h-[44px] rounded-xl border border-stone px-3" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الحالة') }}<select v-model="form.status" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="draft">{{ $t('مسودة') }}</option><option value="published">{{ $t('منشور') }}</option></select></label>
        <button type="submit" class="min-h-[48px] rounded-xl bg-ink font-bold text-white md:col-span-2">{{ $t('احفظ السؤال') }}</button>
      </form>
    </Panel>
    <Panel v-for="q in questions.data" :key="q.id" class="mt-4">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <p class="font-bold">{{ t(q.stem) }}</p>
        <button v-if="q.status !== 'retired'" type="button" class="min-h-[34px] rounded-lg border border-stone px-3 text-xs" @click="router.post(`/platform/questions/${q.id}/retire`, {}, { preserveScroll: true })">{{ $t('تقاعد') }}</button>
      </div>
      <ul class="text-sm"><li v-for="o in q.options" :key="o.position" class="flex justify-between border-b border-[#F0EDE9] py-1"><span>{{ t(o.label) }}</span><b>{{ o.score }}</b></li></ul>
    </Panel>
  </AdminShell>
</template>
