<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { ar } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  metrics: {
    tenants: Record<string, number>; active_users: number; runs: number; runs_failed: number; credits: number;
    tokens_by_provider: { provider: string; input: number; output: number; runs: number }[]; p95_latency_ms: number | null;
  };
}>();
const totalTenants = Object.values(props.metrics.tenants).reduce((a, b) => a + b, 0);
const QUICK = [[$t('المؤسسات'), '/platform/tenants'], [$t('الباقات'), '/platform/plans'], [$t('مزوّدو AI'), '/platform/providers'], [$t('سجل التدقيق'), '/platform/audit']];
const STATUS: Record<string, string> = { active: $t('نشطة'), trial: $t('تجريبية'), suspended: $t('موقوفة'), cancelled: $t('ملغاة'), archived: $t('مؤرشفة') };
const failRate = props.metrics.runs ? Math.round((100 * props.metrics.runs_failed) / props.metrics.runs) : 0;
</script>

<template>
  <AdminShell portal="platform" active="dashboard" :title="$t('لوحة المنصة')" :subtitle="$t('آخر ٣٠ يوم على كل المؤسسات.')">
    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-4">
      <StatCard :label="$t('المؤسسات')" :value="ar(totalTenants)" :hint="$t('نشطة {p0}، تجريبية {p1}', { p0: ar(metrics.tenants.active ?? 0), p1: ar(metrics.tenants.trial ?? 0) })" />
      <StatCard :label="$t('مستخدمين نشطين')" :value="ar(metrics.active_users)" />
      <StatCard :label="$t('تشغيلات AI')" :value="ar(metrics.runs)" :hint="$t('فشل {p0}٪', { p0: ar(failRate) })" />
      <StatCard :label="$t('رصيد مستهلك')" :value="ar(metrics.credits)" />
      <StatCard :label="$t('زمن الاستجابة p95')" :value="metrics.p95_latency_ms ? $t('{p0} ث', { p0: ar(Math.round(metrics.p95_latency_ms / 100) / 10) }) : '—'" dark />
    </div>
    <nav :aria-label="$t('وصول سريع')" class="mb-6 flex flex-wrap gap-2">
      <Link v-for="q in QUICK" :key="q[1]" :href="q[1]" class="min-h-[44px] rounded-xl border border-stone bg-white px-4 py-3 text-sm font-bold text-ink no-underline hover:border-indigo hover:bg-mist">{{ $t(q[0]) }}</Link>
    </nav>
    <Panel v-if="totalTenants" :title="$t('المؤسسات حسب الحالة')" class="mb-6">
      <ul class="flex flex-col gap-3">
        <li v-for="(count, status) in metrics.tenants" :key="status">
          <div class="mb-1 flex justify-between text-sm"><span class="font-bold">{{ STATUS[status] ?? status }}</span><span class="text-subtle">{{ ar(count) }}</span></div>
          <div class="h-2 overflow-hidden rounded-full bg-stone" role="img" :aria-label="`${status}: ${count}`"><div class="h-full rounded-full bg-indigo" :style="{ width: Math.round((100 * count) / totalTenants) + '%' }"></div></div>
        </li>
      </ul>
    </Panel>
    <Panel :title="$t('الاستخدام حسب المزوّد')">
      <p v-if="!metrics.tokens_by_provider.length" class="py-6 text-center text-sm text-subtle">{{ $t('ما في تفاصيل استخدام حسب المزوّد لهالفترة.') }}</p>
      <DataTable v-else
        :columns="[{ key: 'provider', label: $t('المزوّد') }, { key: 'runs', label: $t('تشغيلات') }, { key: 'input', label: $t('رموز داخلة') }, { key: 'output', label: $t('رموز خارجة') }]"
        :rows="metrics.tokens_by_provider"
        row-key="provider"
      />
    </Panel>
  </AdminShell>
</template>
