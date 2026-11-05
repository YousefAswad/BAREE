<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, postJson } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ segments: { id: number; name: string; mode: string; users_count: number }[]; fields: string[] }>();

const FIELD_LABELS: Record<string, string> = {
  role_template: $t('قالب الدور'), department_id: $t('القسم'), campus_id: $t('الفرع'), job_title_id: $t('المسمّى'), grades: $t('الصفوف'),
  subjects: $t('المواد'), status: $t('الحالة'), level_rank: $t('مستوى AI'), readiness: $t('الجاهزية'), competency: $t('كفاءة'),
};
const OPS: Record<string, string> = { in: $t('من ضمن'), not_in: $t('مش من ضمن'), overlaps: $t('فيها أي من'), gte: '≥', lte: '≤', below: $t('أقل من'), eq: '=' };

const conditions = reactive([{ field: 'role_template', op: 'in', value: 'teacher', key: '' }]);
const name = ref('');
const count = ref<number | null>(null);
const error = ref('');

function rules() {
  return {
    all: conditions.map((c) => {
      const numeric = ['level_rank', 'readiness', 'competency'].includes(c.field) && ['gte', 'lte', 'below'].includes(c.op);
      const value = numeric ? Number(c.value) : String(c.value).split(',').map((v) => v.trim()).map((v) => (/^\d+$/.test(v) ? Number(v) : v));
      return { field: c.field, op: c.op, value, ...(c.field === 'competency' ? { key: c.key } : {}) };
    }),
  };
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(conditions, () => {
  clearTimeout(timer);
  timer = setTimeout(async () => {
    try { count.value = (await postJson<{ count: number }>('/admin/segments/preview', { rules: rules() })).count; error.value = ''; }
    catch (e) { error.value = (e as Error).message; count.value = null; }
  }, 400);
}, { deep: true, immediate: true });

const form = useForm({ name: '', mode: 'rules', rules: {} as unknown });
const save = () => form.transform(() => ({ name: name.value, mode: 'rules', rules: rules() })).post('/admin/segments', { preserveScroll: true });
</script>

<template>
  <AdminShell portal="institution" active="segments" :title="$t('الفئات')" :subtitle="$t('مجموعات ديناميكية بتتحدّث لحالها، وبتستخدمها لاستهداف التقييمات والمهمات والمحتوى والأدوات.')">
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <Panel :title="$t('فئة جديدة')">
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('اسم الفئة') }}<input v-model="name" required :placeholder="$t('مثلًا: معلّمي الصفوف ٧–٩')" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <p class="text-sm font-bold">{{ $t('كل الشروط التالية لازم تنطبق:') }}</p>
        <div v-for="(c, i) in conditions" :key="i" class="grid grid-cols-[1fr_1fr_1.4fr_auto] gap-2">
          <select v-model="c.field" :aria-label="$t('الحقل {p0}', { p0: i + 1 })" class="min-h-[44px] rounded-xl border border-stone px-2"><option v-for="f in fields" :key="f" :value="f">{{ FIELD_LABELS[f] ?? f }}</option></select>
          <select v-model="c.op" :aria-label="$t('العملية {p0}', { p0: i + 1 })" class="min-h-[44px] rounded-xl border border-stone px-2"><option v-for="(label, op) in OPS" :key="op" :value="op">{{ label }}</option></select>
          <div class="flex gap-2">
            <input v-if="c.field === 'competency'" v-model="c.key" dir="ltr" placeholder="data_analysis" :aria-label="$t('الكفاءة {p0}', { p0: i + 1 })" class="min-h-[44px] w-32 rounded-xl border border-stone px-2" />
            <input v-model="c.value" :aria-label="$t('القيمة {p0}', { p0: i + 1 })" :placeholder="$t('قيم مفصولة بفاصلة')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-2" />
          </div>
          <button type="button" :aria-label="$t('احذف الشرط {p0}', { p0: i + 1 })" class="min-h-[44px] w-11 rounded-xl border border-stone" :disabled="conditions.length === 1" @click="conditions.splice(i, 1)">×</button>
        </div>
        <button type="button" class="min-h-[40px] self-start rounded-xl border border-dashed border-indigo px-4 text-sm font-bold text-indigo" @click="conditions.push({ field: 'department_id', op: 'in', value: '', key: '' })">{{ $t('+ شرط') }}</button>
        <button type="button" class="min-h-[48px] rounded-xl bg-ink font-bold text-white" :disabled="!name || !!error" @click="save">{{ $t('احفظ الفئة') }}</button>
      </Panel>
      <Panel :title="$t('الأعضاء الآن')" dark>
        <p class="font-display text-5xl font-extrabold text-apricot" aria-live="polite">{{ count === null ? '…' : ar(count) }}</p>
        <p v-if="error" role="alert" class="text-sm text-[#FFB4B4]">{{ error }}</p>
        <p class="text-sm text-[#B8BBD4]">{{ $t('بيتحدّث وأنت بتعدّل الشروط، وبعد الحفظ بيتحدّث تلقائيًا كل ليلة.') }}</p>
      </Panel>
    </div>
    <Panel :title="$t('الفئات الموجودة')" class="mt-6">
      <div v-for="s in segments" :key="s.id" class="flex justify-between border-b border-[#F0EDE9] py-2 text-sm"><span class="font-bold">{{ s.name }}</span><span>{{ $t('{p0} عضو', { p0: ar(s.users_count) }) }}</span></div>
    </Panel>
  </AdminShell>
</template>
