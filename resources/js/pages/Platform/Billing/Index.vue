<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { useLocale } from '@/composables/useLocale';
import { t } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ mrr: Record<string, number>; counts: Record<string, number>; openInvoices: Record<string, any>[]; tenants: { id: number; name: string }[]; plans: { id: number; name: Record<string, string> }[] }>();
const { t: $t } = useI18n();
const { n, money, date } = useLocale();
const sub = useForm({ tenant_id: props.tenants[0]?.id ?? null, plan_id: props.plans[0]?.id ?? null, seats: 100, provider: 'manual' });
const refs = reactive<Record<string, string>>({});
const paid = (id: string) => useForm({ reference: refs[id] ?? '' }).post(`/platform/billing/invoices/${id}/paid`, { preserveScroll: true });
</script>

<template>
  <AdminShell portal="platform" active="billing" :title="$t('الفوترة')">
    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-4">
      <StatCard v-for="(v, cur) in mrr" :key="cur" :label="`MRR ${cur}`" :value="money(v, cur as string)" dark />
      <StatCard :label="$t('نشطة')" :value="n(counts.active ?? 0)" />
      <StatCard :label="$t('متأخرة الدفع')" :value="n(counts.past_due ?? 0)" />
    </div>
    <Panel :title="$t('اشتراك جديد')">
      <form class="flex flex-wrap gap-2" @submit.prevent="sub.post('/platform/billing/subscriptions', { preserveScroll: true })">
        <select v-model="sub.tenant_id" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('المؤسسة')"><option v-for="tn in tenants" :key="tn.id" :value="tn.id">{{ tn.name }}</option></select>
        <select v-model="sub.plan_id" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('الباقة')"><option v-for="p in plans" :key="p.id" :value="p.id">{{ t(p.name) }}</option></select>
        <input v-model.number="sub.seats" type="number" min="1" class="min-h-[44px] w-28 rounded-xl border border-stone px-3" :aria-label="$t('المقاعد')" />
        <select v-model="sub.provider" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('طريقة الدفع')"><option value="manual">{{ $t('تحويل بنكي') }}</option><option value="stripe">Stripe</option><option value="hmac">{{ $t('بوابة محلية') }}</option></select>
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-5 font-bold text-white">{{ $t('فعّل') }}</button>
      </form>
    </Panel>
    <DataTable class="mt-6" :columns="[{ key: 'number', label: $t('الفاتورة') }, { key: 'total', label: $t('المبلغ') }, { key: 'due', label: $t('الاستحقاق') }, { key: 'mark', label: $t('تأكيد الدفع') }]" :rows="openInvoices" row-key="public_id">
      <template #cell-total="{ row }">{{ money(row.total_cents, row.currency) }}</template>
      <template #cell-due="{ row }">{{ date(row.due_at) }}</template>
      <template #cell-mark="{ row }"><form class="flex gap-1" @submit.prevent="paid(row.public_id)"><input v-model="refs[row.public_id]" required :placeholder="$t('رقم الحوالة')" class="min-h-[36px] w-32 rounded-lg border border-stone px-2 text-xs" :aria-label="$t('رقم الحوالة')" /><button class="min-h-[36px] rounded-lg bg-ink px-2 text-xs font-bold text-white" type="submit">{{ $t('مدفوعة') }}</button></form></template>
    </DataTable>
  </AdminShell>
</template>
