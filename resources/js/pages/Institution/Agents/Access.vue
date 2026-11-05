<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import { t } from '@/lib/http';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  agents: { id: number; key: string; name: Record<string, string>; category: string; enabled: boolean; department_ids: number[] | null; monthly_credit_cap: number | null; institution_rules: string }[];
  departments: { id: number; name: string }[];
  byok: { key: string; configured: boolean }[];
}>();

const state = reactive(Object.fromEntries(props.agents.map((a) => [a.id, { enabled: a.enabled, department_ids: a.department_ids ?? [], monthly_credit_cap: a.monthly_credit_cap, institution_rules: a.institution_rules }])));
const save = (id: number) => router.put(`/admin/agents/${id}`, { ...state[id], department_ids: state[id].department_ids.length ? state[id].department_ids : null }, { preserveScroll: true });
const secrets = reactive<Record<string, string>>({});
const saveKey = (provider: string) => router.put(`/admin/providers/${provider}/key`, { secret: secrets[provider] }, { preserveScroll: true, onSuccess: () => (secrets[provider] = '') });
</script>

<template>
  <AdminShell portal="institution" active="agents" :title="$t('الوكلاء وقواعد المؤسسة')" :subtitle="$t('قواعد المؤسسة هي الطبقة الثانية: كل وكيل بيلتزم فيها، والموظفين بيشوفوها وما بيعدّلوها.')">
    <div class="grid gap-6 xl:grid-cols-2">
      <Panel v-for="a in agents" :key="a.id" :title="t(a.name)">
        <template #actions><ToggleSwitch v-model="state[a.id].enabled" :label="$t('تفعيل {p0}', { p0: t(a.name) })" /></template>
        <fieldset><legend class="mb-2 text-sm font-bold">{{ $t('الأقسام (فارغ = الكل)') }}</legend>
          <label v-for="d in departments" :key="d.id" class="ml-3 inline-flex min-h-[36px] items-center gap-1 text-sm"><input v-model="state[a.id].department_ids" type="checkbox" :value="d.id" />{{ d.name }}</label></fieldset>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('حد شهري للرصيد') }}<input v-model.number="state[a.id].monthly_credit_cap" type="number" min="0" :placeholder="$t('بدون حد')" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('قواعد المؤسسة لهالوكيل') }}<textarea v-model="state[a.id].institution_rules" rows="4" class="rounded-xl border border-stone p-3 font-normal leading-7" /></label>
        <button type="button" class="min-h-[44px] rounded-xl bg-ink font-bold text-white" @click="save(a.id)">{{ $t('حفظ') }}</button>
      </Panel>
    </div>
    <Panel :title="$t('مفاتيح مؤسستك الخاصة (اختياري)')" class="mt-6">
      <p class="text-sm text-subtle">{{ $t('إذا أضفت مفتاح، كل طلبات مؤسستك لهالمزوّد بتمشي على حسابكم.') }}</p>
      <form v-for="p in byok" :key="p.key" class="flex flex-wrap items-center gap-2" @submit.prevent="saveKey(p.key)">
        <span class="w-28 text-sm font-bold" dir="ltr">{{ p.key }}</span>
        <span class="text-xs" :class="p.configured ? 'text-indigo' : 'text-subtle'">{{ p.configured ? $t('مضبوط') : $t('غير مضبوط') }}</span>
        <input v-model="secrets[p.key]" type="password" autocomplete="off" dir="ltr" :aria-label="$t('مفتاح {p0}', { p0: p.key })" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
        <button type="submit" class="min-h-[44px] rounded-xl border border-stone px-4 font-bold">{{ $t('حفظ') }}</button>
      </form>
    </Panel>
  </AdminShell>
</template>
