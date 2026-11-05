<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ workspaces: { id: number; title: string; kind: string; role: string; updated_at: string }[] }>();
const form = useForm({ kind: 'course', title: '', instructions: '' });
const KIND: Record<string, string> = { course: $t('مساق'), project: $t('مشروع') };
const ROLE: Record<string, string> = { owner: $t('إلك'), editor: $t('محرّر'), viewer: $t('مشاهد') };
</script>

<template>
  <StudioShell :title="$t('مساحات العمل')" active="workspaces">
    <h1 class="font-display text-3xl font-extrabold">{{ $t('مساحات العمل') }}</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Link v-for="w in workspaces" :key="w.id" :href="`/workspaces/${w.id}`" class="flex min-h-[150px] flex-col justify-between rounded-3xl border border-stone bg-white p-5 text-ink no-underline hover:border-indigo">
        <span class="flex justify-between text-xs font-bold"><span class="rounded-full bg-mist px-2 py-1 text-indigo-deep">{{ KIND[w.kind] ?? w.kind }}</span><span class="text-subtle">{{ ROLE[w.role] }}</span></span>
        <strong class="font-display text-xl">{{ w.title }}</strong>
        <span class="text-xs text-subtle">{{ new Date(w.updated_at).toLocaleDateString('ar') }}</span>
      </Link>
      <form class="flex flex-col gap-3 rounded-3xl border-2 border-dashed border-lavender p-5" @submit.prevent="form.post('/workspaces')">
        <strong class="font-display">{{ $t('مساحة جديدة') }}</strong>
        <div class="flex gap-2"><label v-for="k in ['course', 'project']" :key="k" class="flex min-h-[40px] items-center gap-2 rounded-xl border border-stone bg-white px-3 text-sm"><input v-model="form.kind" type="radio" :value="k" />{{ KIND[k] }}</label></div>
        <input v-model="form.title" required :placeholder="$t('مثلًا: رياضيات الصف الثامن')" :aria-label="$t('الاسم')" class="min-h-[44px] rounded-xl border border-stone px-3" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('أنشئ') }}</button>
      </form>
    </div>
  </StudioShell>
</template>
