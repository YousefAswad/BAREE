<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, t } from '@/lib/http';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ agents: { key: string; name: Record<string, string>; category: string; status: string; version: number | null; credits: number | null; providers: string[]; rolling_out: number | null }[] }>();
const open = ref(false);
const form = useForm({ key: '', name_ar: '', name_en: '', category: 'teaching', role_templates: [] as string[] });
</script>

<template>
  <AdminShell portal="platform" active="agents" :title="$t('مدير الوكلاء')" :subtitle="$t('أي تعديل بيمرّ على اختبار قبل ما يوصل لأي مؤسسة.')">
    <template #actions><button type="button" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" @click="open = !open">{{ $t('وكيل جديد') }}</button></template>
    <Panel v-if="open" :title="$t('وكيل جديد')" class="mb-6">
      <form class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3" @submit.prevent="form.post('/platform/agents')">
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('المفتاح') }}<input v-model="form.key" dir="ltr" pattern="[a-z][a-z0-9_]{2,40}" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الاسم بالعربي') }}<input v-model="form.name_ar" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الاسم بالإنجليزي') }}<input v-model="form.name_en" dir="ltr" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الفئة') }}<input v-model="form.category" dir="ltr" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <div class="flex items-end"><button type="submit" class="min-h-[44px] w-full rounded-xl bg-apricot font-bold text-ink">{{ $t('إنشاء') }}</button></div>
      </form>
    </Panel>
    <DataTable :columns="[{ key: 'name', label: $t('الوكيل') }, { key: 'category', label: $t('الفئة') }, { key: 'version', label: $t('الإصدار') }, { key: 'providers', label: $t('المحرّكات') }, { key: 'credits', label: $t('رصيد/تشغيل') }]" :rows="agents" row-key="key">
      <template #cell-name="{ row }"><Link :href="`/platform/agents/${row.key}`" class="font-extrabold text-ink">{{ t(row.name as Record<string, string>) }}</Link></template>
      <template #cell-version="{ row }">{{ row.version ? ar(row.version as number) : $t('مسودة') }}<span v-if="row.rolling_out" class="mr-2 rounded-full bg-[#FDF0E4] px-2 py-0.5 text-xs font-bold text-[#9A4A12]">{{ $t('يُنشر {p0}', { p0: ar(row.rolling_out as number) }) }}</span></template>
      <template #cell-providers="{ row }"><span dir="ltr">{{ (row.providers as string[]).join(' → ') }}</span></template>
    </DataTable>
  </AdminShell>
</template>
