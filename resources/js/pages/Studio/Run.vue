<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { useRunStream } from '@/composables/useRunStream';
import { t } from '@/lib/http';
import { Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  workflow: { id: string; status: string; workspace_id: number | null };
  steps: { key: string; status: string; title: Record<string, string>; output: string | null; output_id: number | null; error?: string }[];
}>();

const { text, finished } = useRunStream(() => props.workflow.id);
watch(finished, () => router.reload({ only: ['workflow', 'steps'] }));
const instruction = reactive<Record<string, string>>({});
const STATUS: Record<string, string> = { pending: $t('بالانتظار'), succeeded: $t('جاهز'), failed: $t('تعذّر'), skipped: $t('متوقف') };
</script>

<template>
  <StudioShell :title="$t('حزمة الدرس')" active="studio">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <h1 class="font-display text-3xl font-extrabold">{{ workflow.status === 'running' || workflow.status === 'queued' ? $t('عم نجهّز الحزمة…') : $t('حزمتك') }}</h1>
      <Link v-if="workflow.workspace_id" :href="`/workspaces/${workflow.workspace_id}`" class="min-h-[44px] rounded-xl border border-stone bg-white px-4 py-2.5 text-sm font-bold">{{ $t('افتح مساحة العمل') }}</Link>
    </div>
    <ol class="flex flex-col gap-4">
      <li v-for="(s, i) in steps" :key="s.key" class="rounded-3xl border bg-white p-6" :class="s.status === 'failed' ? 'border-[#F3B3B3]' : 'border-stone'">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-display flex items-center gap-3 text-lg font-bold"><span class="flex h-8 w-8 items-center justify-center rounded-xl bg-ink text-sm text-apricot">{{ i + 1 }}</span>{{ t(s.title) }}</h2>
          <span class="rounded-full px-3 py-1 text-xs font-bold" :class="s.status === 'succeeded' ? 'bg-mist text-indigo-deep' : s.status === 'failed' ? 'bg-[#FBE3E3] text-[#9B1C1C]' : 'bg-stone text-subtle'">{{ STATUS[s.status] ?? s.status }}</span>
        </div>
        <pre v-if="s.output || text[s.key]" class="max-h-96 overflow-auto whitespace-pre-wrap font-[inherit] leading-8">{{ s.output ?? text[s.key] }}</pre>
        <p v-if="s.error" class="text-sm text-[#9B1C1C]">{{ s.error }}</p>
        <form v-if="s.status === 'succeeded' || s.status === 'failed'" class="mt-3 flex flex-wrap gap-2" @submit.prevent="router.post(`/studio/workflows/${workflow.id}/steps/${s.key}/regenerate`, { instruction: instruction[s.key] }, { preserveScroll: true })">
          <label :for="`ins-${s.key}`" class="sr-only">{{ $t('تعديل مطلوب') }}</label>
          <input :id="`ins-${s.key}`" v-model="instruction[s.key]" :placeholder="$t('أعد التوليد مع ملاحظة، مثلًا: اختصر لثلاث خطوات')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3 text-sm" />
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-4 text-sm font-bold text-white">{{ $t('أعد التوليد') }}</button>
          <Link v-if="s.output_id" :href="`/outputs/${s.output_id}`" class="min-h-[44px] rounded-xl border border-stone px-4 py-2.5 text-sm font-bold">{{ $t('افتح وعدّل') }}</Link>
        </form>
      </li>
    </ol>
  </StudioShell>
</template>
