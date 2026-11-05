<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, postJson, t } from '@/lib/http';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{
  campaigns: { id: number; name: string; status: string; ends_at: string | null; template: { name: Record<string, string> } }[];
  templates: { id: number; key: string; name: Record<string, string> }[];
  departments: { id: number; name: string }[];
  segments: { id: number; name: string }[];
}>();

const form = useForm({ name: '', template_id: null as number | null, departments: [] as number[], segments: [] as number[], role_templates: [] as string[], starts_at: new Date().toISOString().slice(0, 16), ends_at: '' });
const count = ref<number | null>(null);
watch(() => [form.departments, form.segments, form.role_templates], async () => {
  count.value = (await postJson<{ count: number }>('/admin/campaigns/audience', { departments: form.departments, segments: form.segments, role_templates: form.role_templates })).count;
}, { deep: true, immediate: true });
</script>

<template>
  <AdminShell portal="institution" active="campaigns" :title="$t('حملات التقييم')" :subtitle="$t('ادعُ مجموعة موظفين لتقييم بموعد محدد، وتابع الإكمال بدون ما تنكشف النتائج الفردية.')">
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <Panel :title="$t('حملة جديدة')">
        <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="form.post('/admin/campaigns')">
          <label class="flex flex-col gap-1 text-sm font-bold sm:col-span-2">{{ $t('الاسم') }}<input v-model="form.name" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold sm:col-span-2">{{ $t('التقييم') }}<select v-model="form.template_id" required class="min-h-[44px] rounded-xl border border-stone px-3"><option v-for="tp in templates" :key="tp.id" :value="tp.id">{{ t(tp.name) }}</option></select></label>
          <fieldset class="sm:col-span-2"><legend class="mb-2 text-sm font-bold">{{ $t('الأقسام (فارغ = الكل)') }}</legend>
            <label v-for="d in departments" :key="d.id" class="ml-3 inline-flex min-h-[36px] items-center gap-1 text-sm"><input v-model="form.departments" type="checkbox" :value="d.id" />{{ d.name }}</label></fieldset>
          <fieldset class="sm:col-span-2"><legend class="mb-2 text-sm font-bold">{{ $t('الفئات') }}</legend>
            <label v-for="s in segments" :key="s.id" class="ml-3 inline-flex min-h-[36px] items-center gap-1 text-sm"><input v-model="form.segments" type="checkbox" :value="s.id" />{{ s.name }}</label></fieldset>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('البداية') }}<input v-model="form.starts_at" type="datetime-local" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('النهاية') }}<input v-model="form.ends_at" type="datetime-local" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <button type="submit" :disabled="form.processing || !count" class="min-h-[48px] rounded-xl bg-ink font-bold text-white sm:col-span-2">{{ $t('أنشئ الحملة') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('الجمهور')" dark>
        <p class="font-display text-5xl font-extrabold text-apricot" aria-live="polite">{{ count === null ? '…' : ar(count) }}</p>
        <p class="text-sm text-[#B8BBD4]">{{ $t('موظف نشط رح توصلهم الدعوة. داخل نفس النوع "أو"، وبين الأنواع "و".') }}</p>
      </Panel>
    </div>
    <Panel :title="$t('الحملات')" class="mt-6">
      <Link v-for="c in campaigns" :key="c.id" :href="`/admin/campaigns/${c.id}`" class="flex justify-between rounded-xl bg-sand p-3 text-sm font-bold text-ink">{{ c.name }}<span class="text-subtle">{{ c.status }}</span></Link>
    </Panel>
  </AdminShell>
</template>
