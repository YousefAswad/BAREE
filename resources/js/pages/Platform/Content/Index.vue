<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { t } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{ posts: Record<string, any>[]; kinds: string[] }>();
const { t: $t } = useI18n();
const form = useForm({ kind: 'tip', title: '', title_en: '', body: '', body_en: '', cta_url: '', status: 'published', targeting: { roles: [] as string[], levels: [] as number[] } });
</script>

<template>
  <AdminShell portal="platform" active="content" :title="$t('المحتوى العام')" :subtitle="$t('نصائح وأخبار أدوات وقصص لكل المؤسسات، باستهداف حسب الدور والمستوى.')">
    <Panel :title="$t('منشور جديد')">
      <form class="grid gap-3 md:grid-cols-2" @submit.prevent="form.post('/platform/content', { preserveScroll: true, onSuccess: () => form.reset('title', 'title_en', 'body', 'body_en') })">
        <select v-model="form.kind" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('النوع')"><option v-for="k in kinds" :key="k" :value="k">{{ k }}</option></select>
        <input v-model="form.cta_url" dir="ltr" placeholder="https://… / /studio/tools/…" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('الرابط')" />
        <input v-model="form.title" required :placeholder="$t('العنوان')" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('العنوان')" />
        <input v-model="form.title_en" dir="ltr" placeholder="Title (English)" class="min-h-[44px] rounded-xl border border-stone px-3" aria-label="Title (English)" />
        <textarea v-model="form.body" rows="3" :placeholder="$t('النص')" class="rounded-xl border border-stone p-3" :aria-label="$t('النص')" />
        <textarea v-model="form.body_en" rows="3" dir="ltr" placeholder="Body (English)" class="rounded-xl border border-stone p-3" aria-label="Body (English)" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white md:col-span-2">{{ $t('انشر لكل المؤسسات') }}</button>
      </form>
    </Panel>
    <DataTable class="mt-6" :columns="[{ key: 'title', label: $t('العنوان') }, { key: 'status', label: $t('الحالة') }, { key: 'views', label: $t('المشاهدات') }, { key: 'clicks', label: $t('النقرات') }]" :rows="posts">
      <template #cell-title="{ row }">{{ t(row.title) }}</template>
    </DataTable>
  </AdminShell>
</template>
