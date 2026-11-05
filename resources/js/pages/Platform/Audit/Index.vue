<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ logs: { data: Record<string, unknown>[] }; filters: { action?: string; tenant_id?: string } }>();
const action = ref(props.filters.action ?? '');
</script>

<template>
  <AdminShell portal="platform" active="audit" :title="$t('سجل التدقيق')" :subtitle="$t('غير قابل للتعديل أو الحذف.')">
    <form class="mb-4 flex gap-2" @submit.prevent="router.get('/platform/audit', { action }, { preserveState: true })">
      <label for="act" class="sr-only">{{ $t('الإجراء') }}</label>
      <input id="act" v-model="action" dir="ltr" placeholder="agent. / tenant. / support." class="min-h-[44px] w-72 rounded-xl border border-stone bg-white px-3" />
      <button class="min-h-[44px] rounded-xl border border-stone bg-white px-4 font-bold" type="submit">{{ $t('تصفية') }}</button>
    </form>
    <DataTable :columns="[{ key: 'created_at', label: $t('الوقت') }, { key: 'action', label: $t('الإجراء') }, { key: 'tenant_id', label: $t('المؤسسة') }, { key: 'actor_id', label: $t('المنفّذ') }, { key: 'target_type', label: $t('الهدف') }]" :rows="logs.data">
      <template #cell-created_at="{ row }">{{ new Date(row.created_at as string).toLocaleString('ar') }}</template>
      <template #cell-action="{ row }"><code dir="ltr">{{ row.action }}</code></template>
    </DataTable>
  </AdminShell>
</template>
