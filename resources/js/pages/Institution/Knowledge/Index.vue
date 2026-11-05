<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { t } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ documents: Record<string, any>[]; unanswered: string[] }>();
const form = useForm({ title: '', kind: 'policy', body: '', file: null as File | null });
const STATUS: Record<string, string> = { indexing: $t('بيتجهّز'), published: $t('منشور'), failed: $t('تعذّر'), draft: $t('مسودة') };
</script>

<template>
  <AdminShell portal="institution" active="knowledge" :title="$t('وثائق المؤسسة')" :subtitle="$t('السياسات والأدلة اللي بيجاوب منها مركز المعرفة. كل جواب بيذكر الوثيقة اللي استند عليها.')">
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <Panel :title="$t('وثيقة جديدة')">
        <form class="flex flex-col gap-3" @submit.prevent="form.post('/admin/knowledge', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() })">
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('العنوان') }}<input v-model="form.title" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('النوع') }}<select v-model="form.kind" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="policy">{{ $t('سياسة') }}</option><option value="guide">{{ $t('دليل') }}</option><option value="prompt_template">{{ $t('قالب طلب') }}</option></select></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('النص (أو ارفع ملف)') }}<textarea v-model="form.body" rows="6" class="rounded-xl border border-stone p-3 font-normal" /></label>
          <label class="text-sm font-bold">{{ $t('ملف PDF أو Word') }}<input type="file" accept=".pdf,.docx" class="mt-1 block text-sm" @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label>
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('انشر') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('أسئلة ما لقينالها جواب')" dark>
        <ul class="flex flex-col gap-2 text-sm"><li v-for="(q, i) in unanswered" :key="i" class="rounded-xl bg-white/10 p-3">{{ q }}</li></ul>
        <p v-if="!unanswered.length" class="text-sm text-[#B8BBD4]">{{ $t('ولا سؤال لسا.') }}</p>
      </Panel>
    </div>
    <DataTable class="mt-6" :columns="[{ key: 'title', label: $t('العنوان') }, { key: 'kind', label: $t('النوع') }, { key: 'status', label: $t('الحالة') }, { key: 'actions', label: '' }]" :rows="documents">
      <template #cell-title="{ row }">{{ t(row.title) }}</template>
      <template #cell-status="{ row }">{{ STATUS[row.status] ?? row.status }}</template>
      <template #cell-actions="{ row }"><button type="button" class="min-h-[36px] rounded-lg border border-stone px-3 text-xs font-bold" @click="router.delete(`/admin/knowledge/${row.id}`, { preserveScroll: true })">{{ $t('حذف') }}</button></template>
    </DataTable>
  </AdminShell>
</template>
