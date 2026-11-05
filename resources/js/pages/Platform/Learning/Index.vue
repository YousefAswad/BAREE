<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { t } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ paths: any[]; missions: any[]; courses: any[]; documents: any[] }>();
const doc = useForm({ title: '', kind: 'guide', body: '' });
const course = useForm({ key: '', title: '', hours: 1 });
const mission = useForm({ key: '', title: '', description: '', path_id: null as number | null, xp: 100, minutes: 15, difficulty: 2, steps_json: '[{"key":"step1","title":"","instruction":""}]', rubric_json: '[{"criterion":"clarity","weight":50},{"criterion":"privacy","weight":50}]', impact_json: '{"prompting":3}', dataset_json: '' });
function saveMission() {
  mission.transform((d) => ({ key: d.key, title: d.title, description: d.description, path_id: d.path_id, xp: d.xp, minutes: d.minutes, difficulty: d.difficulty,
    steps: JSON.parse(d.steps_json), rubric: JSON.parse(d.rubric_json), competency_impact: JSON.parse(d.impact_json), dataset: d.dataset_json ? JSON.parse(d.dataset_json) : null }))
    .post('/platform/authoring/missions', { preserveScroll: true });
}
const toggle = (type: string, id: number) => router.post(`/platform/learning/${type}/${id}/toggle`, {}, { preserveScroll: true });
</script>

<template>
  <AdminShell portal="platform" active="learning" :title="$t('المحتوى التعليمي')" :subtitle="$t('مسارات المختبر والمهمات والكورسات وموارد مركز المعرفة العامة لكل المؤسسات.')">
    <div class="grid gap-6 lg:grid-cols-3">
      <Panel v-for="group in [['path', $t('المسارات'), paths], ['mission', $t('المهمات'), missions], ['course', $t('الكورسات'), courses]]" :key="group[0] as string" :title="group[1] as string">
        <ul class="flex flex-col gap-2 text-sm"><li v-for="item in group[2] as any[]" :key="item.id" class="flex items-center justify-between gap-2 rounded-xl bg-sand px-3 py-2">
          <span>{{ t(item.title) }}</span>
          <button type="button" class="min-h-[34px] rounded-lg border border-stone bg-white px-2 text-xs font-bold" @click="toggle(group[0] as string, item.id)">{{ item.status === 'published' ? $t('منشور') : $t('مسودة') }}</button></li></ul>
      </Panel>
    </div>
    <Panel :title="$t('مورد جديد لمركز المعرفة')" class="mt-6">
      <form class="flex flex-col gap-3" @submit.prevent="doc.post('/platform/learning/documents', { preserveScroll: true, onSuccess: () => doc.reset() })">
        <input v-model="doc.title" required :placeholder="$t('العنوان')" :aria-label="$t('العنوان')" class="min-h-[44px] rounded-xl border border-stone px-3" />
        <select v-model="doc.kind" :aria-label="$t('النوع')" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="guide">{{ $t('دليل') }}</option><option value="prompt_template">{{ $t('قالب طلب') }}</option><option value="research">{{ $t('بحث') }}</option></select>
        <textarea v-model="doc.body" required rows="6" placeholder="Markdown" :aria-label="$t('المحتوى')" class="rounded-xl border border-stone p-3" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('انشر لكل المؤسسات') }}</button>
      </form>
    </Panel>
  </AdminShell>
</template>
