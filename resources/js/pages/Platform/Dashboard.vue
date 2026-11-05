<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { ar } from '@/lib/http';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  metrics: {
    tenants: Record<string, number>; active_users: number; runs: number; runs_failed: number; credits: number;
    tokens_by_provider: { provider: string; input: number; output: number; runs: number }[]; p95_latency_ms: number | null;
  };
}>();
const totalTenants = Object.values(props.metrics.tenants).reduce((a, b) => a + b, 0);
const failRate = props.metrics.runs ? Math.round((100 * props.metrics.runs_failed) / props.metrics.runs) : 0;
</script>

<template>
  <AdminShell portal="platform" active="dashboard" :title="$t('لوحة المنصة')" :subtitle="$t('آخر ٣٠ يوم على كل المؤسسات.')">
    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-4">
      <StatCard :label="$t('المؤسسات')" :value="ar(totalTenants)" :hint="$t('نشطة {p0}، تجريبية {p1}', { p0: ar(metrics.tenants.active ?? 0), p1: ar(metrics.tenants.trial ?? 0) })" />
      <StatCard :label="$t('مستخدمين نشطين')" :value="ar(metrics.active_users)" />
      <StatCard :label="$t('تشغيلات AI')" :value="ar(metrics.runs)" :hint="$t('فشل {p0}٪', { p0: ar(failRate) })" />
      <StatCard :label="$t('رصيد مستهلك')" :value="ar(metrics.credits)" />
      <StatCard :label="$t('زمن الاستجابة p95')" :value="metrics.p95_latency_ms ? $t('{p0} ث', { p0: ar(Math.round(metrics.p95_latency_ms / 100) / 10) }) : '—'" dark />
    </div>
    <Panel :title="$t('الاستخدام حسب المزوّد')">
      <DataTable
        :columns="[{ key: 'provider', label: $t('المزوّد') }, { key: 'runs', label: $t('تشغيلات') }, { key: 'input', label: $t('رموز داخلة') }, { key: 'output', label: $t('رموز خارجة') }]"
        :rows="metrics.tokens_by_provider"
        row-key="provider"
      />
    </Panel>
  </AdminShell>
</template>
