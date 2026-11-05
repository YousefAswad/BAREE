<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  output: { id: number; title: string; type: string; version: number; workspace_id: number; body: string; metadata: Record<string, unknown> | null };
  versions: { id: number; version: number; created_at: string; ai_run_id: number | null }[];
  exports: { public_id: string; format: string; status: string; expires_at: string | null }[];
  canEdit: boolean;
}>();

const editing = ref(false);
const edit = useForm({ body: props.output.body });
const refine = useForm({ instruction: '' });
const shareDays = ref(7);
const QUICK = [$t('اختصره'), $t('بسّط اللغة'), $t('أضف نشاط جماعي'), $t('أضف أمثلة من البيئة المحلية')];
</script>

<template>
  <StudioShell :title="output.title" active="workspaces">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <Link :href="`/workspaces/${output.workspace_id}`" class="text-sm font-bold text-indigo">{{ $t('رجوع لمساحة العمل') }}</Link>
        <h1 class="font-display text-3xl font-extrabold">{{ output.title }}</h1>
        <span v-if="output.metadata?.ai_generated" class="mt-1 inline-block rounded-full bg-mist px-3 py-1 text-xs font-bold text-indigo-deep">{{ $t('مُنشأ بالذكاء الاصطناعي، راجعه قبل الاستخدام') }}</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-for="f in ['docx', 'pptx', 'pdf']" :key="f" type="button" class="min-h-[44px] rounded-xl border border-stone bg-white px-4 text-sm font-bold uppercase" @click="router.post(`/outputs/${output.id}/exports`, { format: f }, { preserveScroll: true })">{{ f }}</button>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[3fr_1fr]">
      <section class="rounded-3xl border border-stone bg-white p-6">
        <template v-if="!editing">
          <pre class="whitespace-pre-wrap font-[inherit] leading-8">{{ output.body }}</pre>
          <button v-if="canEdit" type="button" class="mt-4 min-h-[44px] rounded-xl border border-stone px-4 font-bold" @click="editing = true">{{ $t('تعديل يدوي') }}</button>
        </template>
        <form v-else class="flex flex-col gap-3" @submit.prevent="edit.put(`/outputs/${output.id}`)">
          <label for="body" class="sr-only">{{ $t('المحتوى') }}</label>
          <textarea id="body" v-model="edit.body" rows="24" class="rounded-xl border border-stone p-4 font-mono text-sm leading-7" />
          <div class="flex gap-2"><button type="submit" class="min-h-[44px] rounded-xl bg-ink px-5 font-bold text-white">{{ $t('احفظ كإصدار جديد') }}</button><button type="button" class="min-h-[44px] rounded-xl border border-stone px-5 font-bold" @click="editing = false">{{ $t('إلغاء') }}</button></div>
        </form>
      </section>

      <aside class="flex flex-col gap-4">
        <section v-if="canEdit" class="rounded-3xl bg-ink p-5 text-white">
          <h2 class="font-display mb-2 font-bold">{{ $t('عدّل بجملة') }}</h2>
          <div class="mb-2 flex flex-wrap gap-1"><button v-for="q in QUICK" :key="q" type="button" class="min-h-[34px] rounded-full border border-white/20 px-3 text-xs" @click="refine.instruction = q">{{ q }}</button></div>
          <form class="flex flex-col gap-2" @submit.prevent="refine.post(`/outputs/${output.id}/refine`)">
            <label for="ref" class="sr-only">{{ $t('التعديل') }}</label>
            <textarea id="ref" v-model="refine.instruction" required rows="3" class="rounded-xl border border-white/20 bg-transparent p-3 text-sm" />
            <button type="submit" :disabled="refine.processing" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink">{{ refine.processing ? $t('بيشتغل…') : $t('طبّق') }}</button>
          </form>
        </section>
        <section class="rounded-3xl border border-stone bg-white p-5">
          <h2 class="font-display mb-2 font-bold">{{ $t('الإصدارات') }}</h2>
          <ul class="flex flex-col gap-1 text-sm"><li v-for="v in versions" :key="v.id"><Link :href="`/outputs/${v.id}`" class="flex justify-between rounded-lg px-2 py-1.5" :class="v.id === output.id ? 'bg-mist font-bold' : ''"><span>{{ $t('إصدار {p0}', { p0: v.version }) }}</span><span class="text-xs text-subtle">{{ new Date(v.created_at).toLocaleDateString('ar') }}</span></Link></li></ul>
        </section>
        <section class="rounded-3xl border border-stone bg-white p-5">
          <h2 class="font-display mb-2 font-bold">{{ $t('الملفات الجاهزة') }}</h2>
          <ul class="flex flex-col gap-1 text-sm"><li v-for="e in exports" :key="e.public_id" class="flex justify-between"><span class="uppercase">{{ e.format }}</span><a v-if="e.status === 'ready'" :href="`/exports/${e.public_id}`" class="font-bold text-indigo">{{ $t('تنزيل') }}</a><span v-else class="text-subtle">{{ e.status }}</span></li></ul>
        </section>
        <section v-if="canEdit" class="rounded-3xl border border-stone bg-white p-5">
          <h2 class="font-display mb-2 font-bold">{{ $t('رابط للقراءة') }}</h2>
          <form class="flex gap-2" @submit.prevent="router.post(`/outputs/${output.id}/share`, { days: shareDays }, { preserveScroll: true })">
            <label for="days" class="sr-only">{{ $t('المدة بالأيام') }}</label>
            <select id="days" v-model.number="shareDays" class="min-h-[40px] rounded-xl border border-stone px-2 text-sm"><option :value="1">{{ $t('يوم') }}</option><option :value="7">{{ $t('أسبوع') }}</option><option :value="30">{{ $t('شهر') }}</option></select>
            <button type="submit" class="min-h-[40px] flex-1 rounded-xl bg-ink text-sm font-bold text-white">{{ $t('أنشئ رابط') }}</button>
          </form>
        </section>
      </aside>
    </div>
  </StudioShell>
</template>
