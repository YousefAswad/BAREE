<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import { ar } from '@/lib/http';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  campaign: { id: number; name: string; status: string; starts_at: string; ends_at: string | null };
  progress: { department: string | null; invited: number; completed: number | null; rate: number | null }[];
  minGroup: number;
}>();
</script>

<template>
  <AdminShell portal="institution" active="campaigns" :title="campaign.name" :subtitle="$t('أي قسم أقل من {p0} أشخاص بتنخفى نسبته عشان ما ينعرف مين ما كمّل.', { p0: ar(minGroup) })">
    <template #actions>
      <button v-if="campaign.status === 'scheduled'" type="button" class="min-h-[44px] rounded-xl bg-apricot px-5 font-bold text-ink" @click="router.post(`/admin/campaigns/${campaign.id}/launch`)">{{ $t('أطلق الآن') }}</button>
    </template>
    <DataTable :columns="[{ key: 'department', label: $t('القسم') }, { key: 'invited', label: $t('مدعوّين') }, { key: 'completed', label: $t('أكملوا') }, { key: 'rate', label: $t('النسبة') }]" :rows="progress" row-key="department">
      <template #cell-department="{ row }">{{ row.department ?? $t('بدون قسم') }}</template>
      <template #cell-invited="{ row }">{{ ar(row.invited as number) }}</template>
      <template #cell-completed="{ row }">{{ row.completed === null ? $t('مخفي') : ar(row.completed as number) }}</template>
      <template #cell-rate="{ row }">{{ row.rate === null ? '—' : $t('{p0}٪', { p0: ar(row.rate as number) }) }}</template>
    </DataTable>
  </AdminShell>
</template>
