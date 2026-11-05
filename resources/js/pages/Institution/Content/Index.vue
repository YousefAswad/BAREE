<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { useLocale } from '@/composables/useLocale';
import { postJson, t } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{ posts: Record<string, any>[]; departments: { id: number; name: string }[]; segments: { id: number; name: string }[]; kinds: string[] }>();
const { t: $t } = useI18n();
const { n } = useLocale();
const KIND: Record<string, string> = { article: $t('مقال'), tip: $t('نصيحة'), video: $t('فيديو'), news: $t('خبر'), tool: $t('أداة جديدة'), story: $t('قصة نجاح'), event: $t('فعالية'), policy: $t('سياسة') };
const form = useForm({ kind: 'news', title: '', title_en: '', body: '', body_en: '', cta_url: '', status: 'published', targeting: { roles: [] as string[], levels: [] as number[], departments: [] as number[], segments: [] as number[], gap: { competency: '', below: 60 } } });
const reach = ref<number | null>(null);
watch(() => form.targeting, async (targeting) => { reach.value = (await postJson<{ count: number }>('/admin/content/estimate', { targeting })).count; }, { deep: true, immediate: true });
</script>

<template>
  <AdminShell portal="institution" active="content" :title="$t('المحتوى والإعلانات')" :subtitle="$t('منشورات بتوصل بس للي بحاجتها: حسب الدور أو القسم أو المستوى أو فجوة بكفاءة.')">
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <Panel :title="$t('منشور جديد')">
        <form class="flex flex-col gap-3" @submit.prevent="form.post('/admin/content', { preserveScroll: true, onSuccess: () => form.reset('title', 'title_en', 'body', 'body_en', 'cta_url') })">
          <div class="flex flex-wrap gap-1"><button v-for="k in kinds" :key="k" type="button" :aria-pressed="form.kind === k" class="min-h-[36px] rounded-full border px-3 text-sm" :class="form.kind === k ? 'border-ink bg-ink text-white' : 'border-stone'" @click="form.kind = k">{{ $t(KIND[k] ?? k) }}</button></div>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('العنوان') }}<input v-model="form.title" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('العنوان بالإنجليزي (اختياري)') }}<input v-model="form.title_en" dir="ltr" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('النص') }}<textarea v-model="form.body" rows="4" class="rounded-xl border border-stone p-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('رابط (https أو مسار داخلي)') }}<input v-model="form.cta_url" dir="ltr" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <fieldset><legend class="mb-1 text-sm font-bold">{{ $t('الأقسام') }}</legend><label v-for="d in departments" :key="d.id" class="me-3 inline-flex min-h-[36px] items-center gap-1 text-sm"><input v-model="form.targeting.departments" type="checkbox" :value="d.id" />{{ d.name }}</label></fieldset>
          <fieldset><legend class="mb-1 text-sm font-bold">{{ $t('المستويات') }}</legend><label v-for="lv in 6" :key="lv" class="me-3 inline-flex min-h-[36px] items-center gap-1 text-sm"><input v-model="form.targeting.levels" type="checkbox" :value="lv" />{{ n(lv) }}</label></fieldset>
          <div class="flex flex-wrap items-center gap-2 text-sm"><span class="font-bold">{{ $t('فجوة بكفاءة') }}</span>
            <select v-model="form.targeting.gap.competency" class="min-h-[40px] rounded-xl border border-stone px-2" :aria-label="$t('الكفاءة')"><option value="">{{ $t('بدون') }}</option><option value="responsible_ai">responsible_ai</option><option value="verification">verification</option><option value="data_analysis">data_analysis</option><option value="prompting">prompting</option></select>
            <span>{{ $t('أقل من') }}</span><input v-model.number="form.targeting.gap.below" type="number" min="0" max="100" class="min-h-[40px] w-20 rounded-xl border border-stone px-2" :aria-label="$t('الحد')" /></div>
          <div class="flex gap-2"><button type="submit" class="min-h-[44px] rounded-xl bg-ink px-5 font-bold text-white" @click="form.status = 'published'">{{ $t('انشر') }}</button>
            <button type="submit" class="min-h-[44px] rounded-xl border border-stone px-5 font-bold" @click="form.status = 'draft'">{{ $t('مسودة') }}</button></div>
        </form>
      </Panel>
      <Panel :title="$t('الوصول المتوقع')" dark>
        <p class="font-display text-5xl font-extrabold text-apricot" aria-live="polite">{{ reach === null ? '…' : n(reach) }}</p>
        <p class="text-sm text-[#B8BBD4]">{{ $t('موظف نشط') }}</p>
      </Panel>
    </div>
    <DataTable class="mt-6" :columns="[{ key: 'title', label: $t('العنوان') }, { key: 'status', label: $t('الحالة') }, { key: 'views', label: $t('المشاهدات') }, { key: 'clicks', label: $t('النقرات') }]" :rows="posts">
      <template #cell-title="{ row }">{{ t(row.title) }}</template>
    </DataTable>
  </AdminShell>
</template>
