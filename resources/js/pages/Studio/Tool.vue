<script setup lang="ts">
import DynamicForm from '@/components/studio/DynamicForm.vue';
import FileDrop from '@/components/studio/FileDrop.vue';
import StudioShell from '@/components/studio/StudioShell.vue';
import { useRunStream } from '@/composables/useRunStream';
import { postJson, t } from '@/lib/http';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  agent: { key: string; name: Record<string, string>; description: Record<string, string> | null; human_review: boolean; output_types: string[] };
  schema: { fields: any[] };
  steps: { key: string; title: Record<string, string> }[];
  credits: number;
  policy: Record<string, Record<string, boolean>>;
  prefill: Record<string, string>;
  workspaces: { id: number; title: string; kind: string }[];
}>();

const inputs = reactive<Record<string, unknown>>({ ...props.prefill });
const workspaceId = ref<number | null>(props.workspaces[0]?.id ?? null);
const task = ref('');
const files = ref<{ id: number; name: string; status: string }[]>([]);
const error = ref('');
const busy = ref(false);
const streamId = ref<string | null>(null);
const { text, finished } = useRunStream(() => streamId.value);
const output = ref<string | null>(null);

const ready = computed(() => files.value.every((f) => f.status === 'indexed' || f.status === 'rejected'));

async function submit() {
  error.value = '';
  busy.value = true;
  output.value = null;
  try {
    const res = await postJson<{ kind: string; url?: string; stream?: string }>(`/studio/tools/${props.agent.key}/runs`, {
      inputs, workspace_id: workspaceId.value, task_instructions: task.value || null, file_ids: files.value.filter((f) => f.status === 'indexed').map((f) => f.id),
    });
    if (res.kind === 'workflow' && res.url) { router.visit(res.url); return; }
    streamId.value = res.stream ?? null;
  } catch (e) {
    error.value = (e as Error).message;
    busy.value = false;
  }
}

watch(finished, async (f) => {
  if (!f) return;
  busy.value = false;
  if (f.status !== 'succeeded' || !f.runId) { error.value = f.message ?? $t('ما قدرنا نكمّل الطلب.'); return; }
  const res = await fetch(`/studio/runs/${f.runId}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  output.value = (await res.json()).output; // names restored only here, for you
});
</script>

<template>
  <StudioShell :title="t(agent.name)" active="studio">
    <div>
      <h1 class="font-display text-3xl font-extrabold">{{ t(agent.name) }}</h1>
      <p class="mt-1 leading-7 text-subtle">{{ t(agent.description) }}</p>
      <Link :href="`/studio/tools/${agent.key}/settings`" class="mt-2 inline-block text-sm font-bold text-indigo">{{ $t('خصّص الأداة لإلك') }}</Link>
      <p v-if="agent.human_review" class="mt-3 rounded-2xl bg-[#FDF0E4] p-3 text-sm text-[#6B330B]">{{ $t('أداة حساسة: الذكاء الاصطناعي بيساعد، والقرار النهائي إلك.') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <form class="flex flex-col gap-6 rounded-3xl border border-stone bg-white p-6" @submit.prevent="submit">
        <DynamicForm v-model="inputs" :fields="schema.fields" />
        <label v-if="policy.task?.instructions !== false" class="flex flex-col gap-1 text-sm font-bold">{{ $t('تعليمات لهالمهمة (اختياري)') }}
          <textarea v-model="task" rows="3" maxlength="4000" :placeholder="$t('مثلًا: ركّز على أمثلة من السوق المحلي')" class="rounded-xl border border-stone p-3 font-normal" /></label>
        <div><p class="mb-2 text-sm font-bold">{{ $t('ملفات مرجعية لهالمهمة') }}</p><FileDrop v-model="files" :workspace-id="workspaceId" /></div>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('احفظ النتيجة في') }}
          <select v-model="workspaceId" class="min-h-[48px] rounded-xl border border-stone px-3 font-normal">
            <option :value="null">{{ $t('ما بدّي أحفظ') }}</option>
            <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.title }}</option>
          </select></label>
        <p v-if="error" role="alert" class="rounded-2xl bg-[#FBE3E3] p-3 text-sm text-[#9B1C1C]">{{ error }}</p>
        <button type="submit" :disabled="busy || !ready" class="min-h-[56px] rounded-2xl bg-apricot text-lg font-bold text-ink disabled:opacity-50">{{ busy ? $t('بيشتغل…') : $t('ولّد ({p0} رصيد)', { p0: credits }) }}</button>
      </form>

      <aside class="flex flex-col gap-4">
        <section v-if="steps.length" class="rounded-3xl bg-ink p-6 text-white">
          <h2 class="font-display mb-3 text-lg font-bold">{{ $t('رح ينتج') }}</h2>
          <ol class="flex flex-col gap-2 text-sm"><li v-for="(s, i) in steps" :key="s.key" class="flex gap-3"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-apricot text-xs font-extrabold text-ink">{{ i + 1 }}</span>{{ t(s.title) }}</li></ol>
        </section>
        <section class="rounded-3xl border border-stone bg-white p-6 text-sm leading-7">
          <h2 class="font-display mb-2 text-lg font-bold">{{ $t('الوكيل رح يستخدم') }}</h2>
          <ul class="list-disc pr-5"><li>{{ $t('تدريب BAREE لهالأداة') }}</li><li>{{ $t('قواعد مؤسستك') }}</li><li>{{ $t('تعليمات مساحة العمل وملفاتها') }}</li><li>{{ $t('تفضيلاتك الشخصية') }}</li><li>{{ $t('ملفات وتعليمات هالمهمة') }}</li></ul>
          <p class="mt-2 text-xs text-subtle">{{ $t('أسماء الطلاب والبيانات الشخصية بتتحوّل لرموز قبل الإرسال.') }}</p>
        </section>
      </aside>
    </div>

    <section v-if="busy || output" aria-live="polite" class="rounded-3xl border border-stone bg-white p-6">
      <h2 class="font-display mb-3 text-lg font-bold">{{ output ? $t('النتيجة') : $t('بيكتب…') }}</h2>
      <pre class="whitespace-pre-wrap font-[inherit] leading-8">{{ output ?? text.main ?? '' }}</pre>
    </section>
  </StudioShell>
</template>
