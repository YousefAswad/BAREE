<script setup lang="ts">
import FileDrop from '@/components/studio/FileDrop.vue';
import StudioShell from '@/components/studio/StudioShell.vue';
import { t } from '@/lib/http';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  agent: { key: string; name: Record<string, string> };
  policy: Record<string, Record<string, boolean>>;
  institutionRules: string | null;
  instructions: string | null;
  files: { id: number; original_name: string; status: string; usage_mode: string }[];
  suggestions: string[];
}>();
const form = useForm({ instructions: props.instructions ?? '' });
const canInstruct = props.policy.user?.instructions !== false;
const canFiles = props.policy.user?.files !== false;
const uploads = ref<{ id: number; name: string; status: string }[]>([]);
const upload = useForm({ file: null as File | null, usage: 'always' });
</script>

<template>
  <StudioShell :title="t(agent.name)" active="studio">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div><p class="text-sm font-bold text-indigo">{{ $t('تخصيص شخصي') }}</p><h1 class="font-display text-3xl font-extrabold">{{ t(agent.name) }}</h1></div>
      <Link :href="`/studio/tools/${agent.key}`" class="min-h-[44px] rounded-xl bg-apricot px-5 py-2.5 font-bold text-ink">{{ $t('استخدم الأداة') }}</Link>
    </div>
    <ol class="grid gap-3 md:grid-cols-2">
      <li class="rounded-2xl bg-ink p-5 text-white"><p class="text-xs font-bold text-apricot">{{ $t('١ · تدريب BAREE') }}</p><p class="mt-1 text-sm text-[#CCCFE0]">{{ $t('ثابت ومختبر. ما حدا بيقدر يعدّله.') }}</p></li>
      <li class="rounded-2xl border border-stone bg-white p-5"><p class="text-xs font-bold text-indigo">{{ $t('٢ · قواعد مؤسستك') }}</p>
        <p class="mt-1 whitespace-pre-line text-sm text-subtle">{{ institutionRules || $t('ما في قواعد إضافية من مؤسستك.') }}</p></li>
    </ol>
    <section class="rounded-3xl border-2 border-indigo bg-white p-6">
      <p class="text-xs font-bold text-indigo">{{ $t('٤ · تعليماتك الشخصية') }}</p>
      <template v-if="canInstruct">
        <p class="mb-2 text-sm text-subtle">{{ $t('بتنطبق على كل مرة بتستخدم فيها هالأداة، بعد قواعد المؤسسة.') }}</p>
        <div class="mb-2 flex flex-wrap gap-1"><button v-for="s in suggestions" :key="s" type="button" class="min-h-[34px] rounded-full border border-stone px-3 text-xs" @click="form.instructions = (form.instructions ? form.instructions + '\n' : '') + s">+ {{ s }}</button></div>
        <form class="flex flex-col gap-2" @submit.prevent="form.put(`/studio/tools/${agent.key}/settings`, { preserveScroll: true })">
          <label for="ins" class="sr-only">{{ $t('تعليماتك') }}</label>
          <textarea id="ins" v-model="form.instructions" rows="6" maxlength="4000" class="rounded-xl border border-stone p-3 leading-7" />
          <button type="submit" class="min-h-[44px] self-start rounded-xl bg-ink px-5 font-bold text-white">{{ $t('حفظ') }}</button>
        </form>
      </template>
      <p v-else class="text-sm text-subtle">{{ $t('هالأداة ما بتقبل تعليمات شخصية.') }}</p>
    </section>
    <section v-if="canFiles" class="rounded-3xl border border-stone bg-white p-6">
      <p class="mb-2 text-xs font-bold text-indigo">{{ $t('ملفاتك المرجعية لهالأداة') }}</p>
      <ul class="mb-3 flex flex-col gap-2 text-sm"><li v-for="f in files" :key="f.id" class="flex items-center justify-between rounded-xl bg-sand px-3 py-2"><span>{{ f.original_name }}</span>
        <button type="button" class="min-h-[34px] rounded-lg border border-stone px-2 text-xs" @click="router.delete(`/studio/personal-files/${f.id}`, { preserveScroll: true })">{{ $t('حذف') }}</button></li></ul>
      <form class="flex flex-wrap gap-2" @submit.prevent="upload.post(`/studio/tools/${agent.key}/settings/files`, { forceFormData: true, preserveScroll: true, onSuccess: () => upload.reset() })">
        <label class="text-sm">{{ $t('ملف') }}<input type="file" accept=".pdf,.docx,.pptx,.xlsx,.txt,.md" class="ms-2 text-sm" @change="upload.file = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label>
        <button type="submit" class="min-h-[40px] rounded-xl border border-stone px-4 text-sm font-bold">{{ $t('ارفع') }}</button>
      </form>
    </section>
  </StudioShell>
</template>
