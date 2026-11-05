<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { useLocale } from '@/composables/useLocale';
import { t } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
  subscription: Record<string, any> | null; seatsUsed: number; credits: number; bankInstructions: string;
  invoices: { public_id: string; number: string; currency: string; total_cents: number; status: string; due_at: string; paid_at: string | null }[];
  plans: { id: number; key: string; name: Record<string, string>; price_per_seat_cents: number; currency: string; interval: string }[];
}>();
const { t: $t } = useI18n();
const { n, money, date } = useLocale();
const seats = useForm({ extra: 10 });
const change = useForm({ plan_id: props.plans[0]?.id ?? null, seats: props.subscription?.seats ?? 100 });
const STATUS: Record<string, string> = { open: $t('مستحقة'), paid: $t('مدفوعة'), void: $t('ملغاة') };
</script>

<template>
  <AdminShell portal="institution" active="billing" :title="$t('الاشتراك والفواتير')">
    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-4">
      <StatCard :label="$t('الباقة')" :value="subscription ? t(subscription.plan?.name) : '—'" :hint="subscription ? $t(subscription.status) : ''" />
      <StatCard :label="$t('المقاعد')" :value="subscription ? `${n(seatsUsed)} / ${n(subscription.seats)}` : n(seatsUsed)" />
      <StatCard :label="$t('رصيد AI')" :value="n(credits)" dark />
      <StatCard :label="$t('التجديد')" :value="date(subscription?.period_end ?? null)" />
    </div>
    <div v-if="subscription" class="grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('مقاعد إضافية')">
        <form class="flex gap-2" @submit.prevent="seats.post('/admin/billing/seats', { preserveScroll: true })">
          <input v-model.number="seats.extra" type="number" min="1" class="min-h-[44px] w-32 rounded-xl border border-stone px-3" :aria-label="$t('عدد المقاعد')" />
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white">{{ $t('أضف الآن (فاتورة تناسبية)') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('تغيير الباقة عند التجديد')">
        <form class="flex flex-wrap gap-2" @submit.prevent="change.post('/admin/billing/change', { preserveScroll: true })">
          <select v-model="change.plan_id" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('الباقة')"><option v-for="p in plans" :key="p.id" :value="p.id">{{ t(p.name) }} · {{ money(p.price_per_seat_cents, p.currency) }}</option></select>
          <input v-model.number="change.seats" type="number" min="1" class="min-h-[44px] w-28 rounded-xl border border-stone px-3" :aria-label="$t('المقاعد')" />
          <button type="submit" class="min-h-[44px] rounded-xl border border-stone px-4 font-bold">{{ $t('جدولة') }}</button>
        </form>
      </Panel>
    </div>
    <DataTable class="mt-6" :columns="[{ key: 'number', label: $t('الفاتورة') }, { key: 'total', label: $t('المبلغ') }, { key: 'due_at', label: $t('الاستحقاق') }, { key: 'status', label: $t('الحالة') }]" :rows="invoices" row-key="public_id">
      <template #cell-total="{ row }">{{ money(row.total_cents as number, row.currency as string) }}</template>
      <template #cell-due_at="{ row }">{{ date(row.due_at as string) }}</template>
      <template #cell-status="{ row }">
        <button v-if="row.status === 'open'" type="button" class="min-h-[36px] rounded-lg bg-apricot px-3 text-xs font-bold text-ink" @click="router.post(`/admin/billing/invoices/${row.public_id}/pay`)">{{ $t('ادفع') }}</button>
        <span v-else>{{ $t(STATUS[row.status as string] ?? (row.status as string)) }}</span>
      </template>
    </DataTable>
    <p v-if="bankInstructions" class="mt-4 whitespace-pre-line rounded-2xl bg-white p-4 text-sm">{{ bankInstructions }}</p>
  </AdminShell>
</template>
