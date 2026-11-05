<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { ar } from '@/lib/http';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ import: { id: number; status: string; total: number; valid: number; invited: number; report: { row: number; name: string; email: string; problems: string[] }[] } }>();
const PROBLEMS: Record<string, string> = { invalid_email: $t('بريد غير صحيح'), duplicate_in_file: $t('مكرّر بالملف'), already_a_user: $t('مستخدم موجود'), unknown_department: $t('قسم غير معروف'), unknown_job_title: $t('مسمّى غير معروف') };
</script>

<template>
  <AdminShell portal="institution" active="users" :title="$t('مراجعة ملف الموظفين')" :subtitle="$t('بس الصفوف السليمة رح توصلها دعوة.')">
    <template #actions>
      <button v-if="props.import.status === 'ready'" type="button" class="min-h-[44px] rounded-xl bg-apricot px-5 font-bold text-ink" :disabled="!props.import.valid" @click="router.post(`/admin/imports/${props.import.id}/commit`)">{{ $t('أرسل {p0} دعوة', { p0: ar(props.import.valid) }) }}</button>
    </template>
    <div class="mb-6 grid grid-cols-3 gap-4">
      <StatCard :label="$t('بالملف')" :value="ar(props.import.total)" />
      <StatCard :label="$t('سليمة')" :value="ar(props.import.valid)" />
      <StatCard :label="$t('أُرسلت')" :value="ar(props.import.invited)" dark />
    </div>
    <DataTable :columns="[{ key: 'row', label: $t('الصف') }, { key: 'name', label: $t('الاسم') }, { key: 'email', label: $t('البريد') }, { key: 'problems', label: $t('الفحص') }]" :rows="props.import.report" row-key="row">
      <template #cell-problems="{ row }">
        <span v-if="!(row.problems as string[]).length" class="rounded-full bg-mist px-2 py-1 text-xs font-bold text-indigo-deep">{{ $t('جاهز') }}</span>
        <span v-for="p in row.problems as string[]" :key="p" class="ml-1 rounded-full bg-[#FDF0E4] px-2 py-1 text-xs font-bold text-[#9A4A12]">{{ PROBLEMS[p] ?? p }}</span>
      </template>
    </DataTable>
  </AdminShell>
</template>
