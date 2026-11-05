<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
  active_by_month: Record<string, number>; outputs_by_type: Record<string, number>; hours_saved_estimate: number; learning_hours: number;
  by_department: { department: string | null; runs: number | null; people: number | null; credits: number; suppressed: boolean }[];
  readiness_trend: { month: string; readiness: number }[]; minGroup: number;
}>();
const { t: $t } = useI18n();
const { n } = useLocale();
const maxActive = computed(() => Math.max(1, ...Object.values(props.active_by_month)));
const totalOutputs = computed(() => Object.values(props.outputs_by_type).reduce((a, b) => a + b, 0));
</script>

<template>
  <AdminShell portal="institution" active="analytics" :title="$t('الاستخدام والأثر')" :subtitle="$t('أي قسم أقل من {k} أشخاص بيظهر مجمّع بدون تفاصيل.', { k: n(minGroup) })">
    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-4">
      <StatCard :label="$t('مخرجات آخر ٩٠ يوم')" :value="n(totalOutputs)" />
      <StatCard :label="$t('ساعات موفّرة')" :value="`~${n(hours_saved_estimate)}`" :hint="$t('تقدير حسب نوع المهام')" />
      <StatCard :label="$t('ساعات تطوير مهني')" :value="n(learning_hours)" dark />
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('نشطين شهريًا')">
        <div class="flex h-48 items-end gap-3" role="img" :aria-label="$t('نشطين شهريًا')">
          <div v-for="(v, m) in active_by_month" :key="m" class="flex flex-1 flex-col items-center gap-1"><span class="text-xs font-bold">{{ n(v) }}</span>
            <span class="w-full max-w-[44px] rounded-t-lg bg-indigo" :style="{ height: `${(v / maxActive) * 150}px` }" /><span class="text-xs text-subtle">{{ m.slice(5) }}</span></div>
        </div>
      </Panel>
      <Panel :title="$t('حسب القسم (آخر ٣٠ يوم)')">
        <div v-for="d in by_department" :key="d.department ?? 'none'" class="flex justify-between border-b border-[#F0EDE9] py-2 text-sm">
          <span class="font-bold">{{ d.department ?? $t('بدون قسم') }}</span>
          <span>{{ d.suppressed ? $t('مجمّع') : `${n(d.runs)} ${$t('تشغيل')} · ${n(d.people)} ${$t('شخص')}` }}</span></div>
      </Panel>
    </div>
  </AdminShell>
</template>
