<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, t } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Version = {
  id: number; version: number; status: string; system_prompt: string; prompt_block_keys: string[] | null;
  input_schema: { fields: unknown[] }; routing: { providers: string[]; tier: string; max_tokens?: number };
  customization_policy: Record<string, Record<string, boolean>>; credits_cost: number; rollout: Record<string, unknown> | null; release_notes: string | null;
};
const props = defineProps<{
  agent: { key: string; name: Record<string, string>; status: string; published_version_id: number | null };
  versions: Version[];
  testCases: { id: number; name: string; category: string; blocking: boolean; active: boolean; assertions: { type: string; value: unknown }[] }[];
  latestRuns: Record<string, { test_case_id: number; status: string; checks: { type: string; ok: boolean }[]; provider: string | null; latency_ms: number | null }[]>;
  providers: string[];
  promptBlocks: string[];
}>();

const STATUS: Record<string, string> = { draft: $t('مسودة'), testing: $t('قيد الاختبار'), rolling_out: $t('نشر تدريجي'), published: $t('منشور'), retired: $t('متقاعد') };
const selectedId = ref(props.versions[0]?.id);
const selected = computed(() => props.versions.find((v) => v.id === selectedId.value));
const editable = computed(() => selected.value?.status === 'draft');

const form = useForm({ system_prompt: '', prompt_block_keys: [] as string[], input_schema_json: '', providers: '', tier: 'standard', max_tokens: 4000, credits_cost: 1, release_notes: '' });
watch(selected, (v) => {
  if (!v) return;
  form.system_prompt = v.system_prompt;
  form.prompt_block_keys = v.prompt_block_keys ?? [];
  form.input_schema_json = JSON.stringify(v.input_schema, null, 2);
  form.providers = v.routing.providers.join(', ');
  form.tier = v.routing.tier;
  form.max_tokens = v.routing.max_tokens ?? 4000;
  form.credits_cost = v.credits_cost;
  form.release_notes = v.release_notes ?? '';
}, { immediate: true });

const schemaError = ref('');
function save() {
  let schema;
  try { schema = JSON.parse(form.input_schema_json); schemaError.value = ''; } catch { schemaError.value = $t('صيغة JSON غير صحيحة'); return; }
  form.transform((d) => ({
    system_prompt: d.system_prompt, prompt_block_keys: d.prompt_block_keys, input_schema: schema, credits_cost: d.credits_cost, release_notes: d.release_notes,
    routing: { providers: d.providers.split(',').map((s) => s.trim()).filter(Boolean), tier: d.tier, max_tokens: d.max_tokens },
  })).patch(`/platform/versions/${selected.value!.id}`, { preserveScroll: true });
}

const rollout = useForm({ mode: 'pilot', tenant_ids: '' as string, percent: 25 });
function startRollout() {
  rollout.transform((d) => ({ mode: d.mode, percent: d.percent, tenant_ids: d.tenant_ids.split(',').map((s) => Number(s.trim())).filter(Boolean) }))
    .post(`/platform/versions/${selected.value!.id}/rollout`, { preserveScroll: true });
}
const act = (path: string) => router.post(`/platform/versions/${selected.value!.id}/${path}`, {}, { preserveScroll: true });

const runs = computed(() => (selected.value ? props.latestRuns[selected.value.id] ?? [] : []));
const caseName = (id: number) => props.testCases.find((c) => c.id === id)?.name ?? id;

const testCase = useForm({ name: '', category: 'quality', inputs_json: '{\n  "grade": "الرابع"\n}', assertions_json: '[{"type":"contains","value":"## "}]', blocking: true });
function addCase() {
  testCase.transform((d) => ({ name: d.name, category: d.category, blocking: d.blocking, inputs: JSON.parse(d.inputs_json), assertions: JSON.parse(d.assertions_json) }))
    .post(`/platform/agents/${props.agent.key}/test-cases`, { preserveScroll: true, onSuccess: () => testCase.reset('name') });
}
</script>

<template>
  <AdminShell portal="platform" active="agents" :title="t(agent.name)" :subtitle="$t('المسودة بتتعدّل، بعدين بتتختبر، بعدين بتنتشر تدريجيًا.')">
    <template #actions>
      <button type="button" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" @click="router.post(`/platform/agents/${agent.key}/drafts`, {}, { preserveScroll: true })">{{ $t('مسودة جديدة') }}</button>
    </template>

    <div class="mb-6 flex flex-wrap gap-2" role="tablist" :aria-label="$t('الإصدارات')">
      <button v-for="v in versions" :key="v.id" type="button" role="tab" :aria-selected="v.id === selectedId"
        class="min-h-[44px] rounded-xl border px-4 text-sm font-bold" :class="v.id === selectedId ? 'border-ink bg-ink text-white' : 'border-stone bg-white'"
        @click="selectedId = v.id">{{ $t('إصدار {p0} · {p1}', { p0: ar(v.version), p1: STATUS[v.status] }) }}</button>
    </div>

    <div v-if="selected" class="grid gap-6 xl:grid-cols-[2fr_1fr]">
      <Panel :title="editable ? $t('تحرير المسودة') : $t('الإصدار (للقراءة)')">
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('التدريب الأساسي (الطبقة ١)') }}
          <textarea v-model="form.system_prompt" :readonly="!editable" rows="12" dir="auto" class="rounded-xl border border-stone p-3 font-mono text-sm font-normal leading-7" /></label>
        <fieldset class="flex flex-wrap gap-3">
          <legend class="mb-2 text-sm font-bold">{{ $t('مكوّنات مشتركة') }}</legend>
          <label v-for="b in promptBlocks" :key="b" class="flex items-center gap-2 text-sm"><input v-model="form.prompt_block_keys" type="checkbox" :value="b" :disabled="!editable" /> <code dir="ltr">{{ b }}</code></label>
        </fieldset>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('حقول الإدخال (JSON)') }}
          <textarea v-model="form.input_schema_json" :readonly="!editable" rows="8" dir="ltr" class="rounded-xl border border-stone p-3 font-mono text-xs font-normal" /></label>
        <p v-if="schemaError" role="alert" class="text-sm text-[#9B1C1C]">{{ schemaError }}</p>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-3">
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('المزوّدون بالترتيب') }}<input v-model="form.providers" :readonly="!editable" dir="ltr" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('المستوى') }}<select v-model="form.tier" :disabled="!editable" class="min-h-[44px] rounded-xl border border-stone px-3"><option>light</option><option>standard</option><option>advanced</option></select></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('أقصى رموز') }}<input v-model.number="form.max_tokens" type="number" :readonly="!editable" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('رصيد/تشغيل') }}<input v-model.number="form.credits_cost" type="number" min="1" :readonly="!editable" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        </div>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('ملاحظات الإصدار') }}<textarea v-model="form.release_notes" :readonly="!editable" rows="2" class="rounded-xl border border-stone p-3 font-normal" /></label>
        <div v-if="editable" class="flex flex-wrap gap-2">
          <button type="button" class="min-h-[44px] rounded-xl border border-stone px-4 font-bold" @click="save">{{ $t('حفظ المسودة') }}</button>
          <button type="button" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" @click="act('submit')">{{ $t('أرسل للاختبار') }}</button>
        </div>
      </Panel>

      <div class="flex flex-col gap-6">
        <Panel :title="$t('الاختبار')" dark>
          <button type="button" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink" :disabled="selected.status === 'draft'" @click="act('tests')">{{ $t('شغّل كل الحالات') }}</button>
          <ul class="flex flex-col gap-2 text-sm">
            <li v-for="r in runs" :key="r.test_case_id" class="flex justify-between gap-2 rounded-xl bg-white/10 p-3">
              <span>{{ caseName(r.test_case_id) }}</span>
              <span class="font-extrabold" :class="r.status === 'passed' ? 'text-apricot' : 'text-[#FFB4B4]'">{{ r.status === 'passed' ? $t('ناجح') : r.status === 'failed' ? $t('فاشل') : $t('خطأ') }}</span>
            </li>
            <li v-if="!runs.length" class="text-[#B8BBD4]">{{ $t('ما في نتائج لهالإصدار بعد.') }}</li>
          </ul>
        </Panel>
        <Panel v-if="['testing', 'rolling_out'].includes(selected.status)" :title="$t('النشر')">
          <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="$t('طريقة النشر')">
            <label v-for="m in [['pilot', $t('مؤسسات تجريبية')], ['percent', $t('نسبة')], ['all', $t('الكل')]]" :key="m[0]" class="flex min-h-[40px] items-center gap-2 rounded-xl border border-stone px-3 text-sm"><input v-model="rollout.mode" type="radio" :value="m[0]" />{{ m[1] }}</label>
          </div>
          <input v-if="rollout.mode === 'pilot'" v-model="rollout.tenant_ids" dir="ltr" :placeholder="$t('أرقام المؤسسات: 3, 7')" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('المؤسسات التجريبية')" />
          <input v-if="rollout.mode === 'percent'" v-model.number="rollout.percent" type="number" min="1" max="99" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('النسبة')" />
          <button type="button" class="min-h-[44px] rounded-xl bg-ink font-bold text-white" @click="startRollout">{{ $t('ابدأ النشر') }}</button>
          <button v-if="selected.status === 'rolling_out'" type="button" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink" @click="act('promote')">{{ $t('انشر للكل') }}</button>
        </Panel>
        <Panel v-if="selected.status === 'retired'" :title="$t('الرجوع')">
          <button type="button" class="min-h-[44px] rounded-xl border border-stone font-bold" @click="act('rollback')">{{ $t('ارجع لهالإصدار') }}</button>
        </Panel>
      </div>
    </div>

    <Panel :title="$t('حالات الاختبار الثابتة')" class="mt-6">
      <DataTable :columns="[{ key: 'name', label: $t('الحالة') }, { key: 'category', label: $t('الفئة') }, { key: 'assertions', label: $t('الشروط') }, { key: 'blocking', label: $t('تمنع النشر') }]" :rows="testCases.filter((c) => c.active)">
        <template #cell-assertions="{ row }"><span dir="ltr" class="text-xs">{{ (row.assertions as { type: string }[]).map((a) => a.type).join(', ') }}</span></template>
        <template #cell-blocking="{ row }">{{ row.blocking ? $t('نعم') : $t('لا') }}</template>
      </DataTable>
      <form class="grid gap-3 md:grid-cols-2" @submit.prevent="addCase">
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('اسم الحالة') }}<input v-model="testCase.name" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الفئة') }}<select v-model="testCase.category" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="quality">{{ $t('جودة') }}</option><option value="safety">{{ $t('سلامة') }}</option><option value="privacy">{{ $t('خصوصية') }}</option><option value="performance">{{ $t('أداء') }}</option></select></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('المدخلات (JSON)') }}<textarea v-model="testCase.inputs_json" rows="5" dir="ltr" class="rounded-xl border border-stone p-3 font-mono text-xs font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الشروط (JSON)') }}<textarea v-model="testCase.assertions_json" rows="5" dir="ltr" class="rounded-xl border border-stone p-3 font-mono text-xs font-normal" /></label>
        <label class="flex items-center gap-2 text-sm font-bold"><input v-model="testCase.blocking" type="checkbox" /> {{ $t('تمنع النشر إذا فشلت') }}</label>
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('أضف الحالة') }}</button>
      </form>
    </Panel>
  </AdminShell>
</template>
