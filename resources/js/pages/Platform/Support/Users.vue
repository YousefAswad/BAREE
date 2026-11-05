<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ session: { id: number; reason: string; expires_at: string }; users: { data: Record<string, unknown>[] } }>();
</script>

<template>
  <AdminShell portal="platform" active="tenants" :title="$t('جلسة دعم: المستخدمين')" :subtitle="$t('قراءة فقط. السبب: {p0}. تنتهي {p1}', { p0: session.reason, p1: new Date(session.expires_at).toLocaleTimeString('ar') })">
    <template #actions>
      <button type="button" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" @click="router.delete(`/platform/support/${session.id}`)">{{ $t('إنهاء الجلسة') }}</button>
    </template>
    <DataTable :columns="[{ key: 'name', label: $t('الاسم') }, { key: 'email', label: $t('البريد') }, { key: 'status', label: $t('الحالة') }]" :rows="users.data" />
  </AdminShell>
</template>
