<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ blocks: { key: string; group: string; agents: number; versions: { id: number; version: number; content: string; status: string }[] }[] }>();
const form = useForm({ key: '', group: 'quality', content: '' });
const STATUS: Record<string, string> = { draft: $t('مسودة'), published: $t('منشور'), retired: $t('متقاعد') };
</script>

<template>
  <AdminShell portal="platform" active="prompts" :title="$t('مكتبة البرومبتات')" :subtitle="$t('مكوّنات مشتركة بين الوكلاء. نشر إصدار جديد بيأثّر على كل وكيل بيستخدمه، فشغّل اختباراتهم بعده.')">
    <Panel :title="$t('مكوّن أو إصدار جديد')">
      <form class="grid gap-3 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="form.post('/platform/prompts', { preserveScroll: true, onSuccess: () => form.reset('content') })">
        <input v-model="form.key" dir="ltr" required placeholder="quality.verification" :aria-label="$t('المفتاح')" class="min-h-[44px] rounded-xl border border-stone px-3" />
        <input v-model="form.group" dir="ltr" required :aria-label="$t('المجموعة')" class="min-h-[44px] rounded-xl border border-stone px-3" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-5 font-bold text-white">{{ $t('احفظ كمسودة') }}</button>
        <textarea v-model="form.content" required rows="5" dir="auto" :aria-label="$t('المحتوى')" class="rounded-xl border border-stone p-3 font-mono text-sm md:col-span-3" />
      </form>
    </Panel>
    <Panel v-for="b in blocks" :key="b.key" class="mt-4" :title="b.key">
      <template #actions><span class="text-xs text-subtle">{{ $t('{p0} · {p1} وكيل', { p0: b.group, p1: b.agents }) }}</span></template>
      <details v-for="v in b.versions" :key="v.id" class="rounded-xl bg-sand p-3">
        <summary class="flex cursor-pointer items-center justify-between gap-2 text-sm font-bold">{{ $t('إصدار {p0} · {p1}', { p0: v.version, p1: STATUS[v.status] ?? v.status }) }}
          <button v-if="v.status === 'draft'" type="button" class="min-h-[34px] rounded-lg bg-apricot px-3 text-xs font-bold text-ink" @click.prevent="router.post(`/platform/prompts/${v.id}/publish`, {}, { preserveScroll: true })">{{ $t('انشر') }}</button></summary>
        <pre dir="auto" class="mt-2 whitespace-pre-wrap text-xs leading-6">{{ v.content }}</pre>
      </details>
    </Panel>
  </AdminShell>
</template>
