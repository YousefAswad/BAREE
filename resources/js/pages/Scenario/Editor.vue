<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { postJson } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Scene = { id: string; type: string; title: string; prompt: string; body: string | null; status: string; media?: { id: string; mime: string | null; status: string } };
const props = defineProps<{ scenario: { id: number; title: string; context: Record<string, string> }; scenes: Scene[]; sceneTypes: string[]; credits: Record<string, number> }>();

const LABEL: Record<string, string> = { text: $t('نص'), image: $t('صورة'), voice: $t('تعليق صوتي'), video: $t('فيديو'), model3d: $t('نموذج 3D'), quiz: $t('سؤال'), activity: $t('نشاط جماعي') };
const COST: Record<string, number> = { image: props.credits.image, voice: props.credits.speech, video: props.credits.video, model3d: props.credits.model3d, text: 2, quiz: 2, activity: 2 };
const add = useForm({ type: 'text', title: '', prompt: '' });
const selected = ref(props.scenes[0]?.id ?? null);
const working = ref<string | null>(null);
const error = ref('');

async function generate(scene: Scene) {
  working.value = scene.id;
  error.value = '';
  try { await postJson(`/scenarios/${props.scenario.id}/scenes/${scene.id}/generate`, {}); router.reload({ only: ['scenes'] }); }
  catch (e) { error.value = (e as Error).message; }
  finally { working.value = null; }
}
function move(index: number, delta: number) {
  const order = props.scenes.map((s) => s.id);
  const [item] = order.splice(index, 1);
  order.splice(index + delta, 0, item);
  router.put(`/scenarios/${props.scenario.id}/order`, { order }, { preserveScroll: true });
}
</script>

<template>
  <StudioShell :title="scenario.title" active="workspaces">
    <div><p class="text-sm font-bold text-indigo">{{ $t('موقف تعليمي · {p0}', { p0: scenario.context.topic }) }}</p><h1 class="font-display text-3xl font-extrabold">{{ scenario.title }}</h1></div>
    <p v-if="error" role="alert" class="rounded-2xl bg-[#FBE3E3] p-3 text-sm text-[#9B1C1C]">{{ error }}</p>

    <ol class="flex gap-3 overflow-x-auto pb-2" :aria-label="$t('المشاهد')">
      <li v-for="(s, i) in scenes" :key="s.id" class="w-56 shrink-0">
        <button type="button" :aria-pressed="selected === s.id" class="flex h-full w-full flex-col gap-1 rounded-2xl border p-4 text-right" :class="selected === s.id ? 'border-2 border-ink bg-white' : 'border-stone bg-white'" @click="selected = s.id">
          <span class="text-xs font-bold text-indigo">{{ i + 1 }} · {{ LABEL[s.type] }}</span>
          <strong class="text-sm">{{ s.title }}</strong>
          <span class="text-xs" :class="s.status === 'ready' ? 'text-indigo' : s.status === 'failed' ? 'text-[#9B1C1C]' : 'text-subtle'">{{ { draft: $t('مسودة'), generating: $t('بيتولّد…'), ready: $t('جاهز'), failed: $t('تعذّر') }[s.status] ?? s.status }}</span>
        </button>
        <div class="mt-1 flex justify-center gap-1">
          <button type="button" :disabled="i === 0" :aria-label="$t('حرّك {p0} لقبل', { p0: s.title })" class="h-8 w-8 rounded-lg border border-stone bg-white disabled:opacity-30" @click="move(i, -1)">→</button>
          <button type="button" :disabled="i === scenes.length - 1" :aria-label="$t('حرّك {p0} لبعد', { p0: s.title })" class="h-8 w-8 rounded-lg border border-stone bg-white disabled:opacity-30" @click="move(i, 1)">←</button>
        </div>
      </li>
    </ol>

    <template v-for="s in scenes" :key="`d-${s.id}`">
      <section v-if="selected === s.id" class="grid gap-6 rounded-3xl border border-stone bg-white p-6 lg:grid-cols-[3fr_2fr]">
        <div class="flex min-h-[260px] items-center justify-center rounded-2xl bg-sand p-4">
          <img v-if="s.media?.status === 'succeeded' && s.media.mime?.startsWith('image/')" :src="`/studio/media/${s.media.id}/file`" :alt="s.title" class="max-h-80 rounded-xl" />
          <audio v-else-if="s.media?.status === 'succeeded' && s.media.mime?.startsWith('audio/')" :src="`/studio/media/${s.media.id}/file`" controls />
          <video v-else-if="s.media?.status === 'succeeded' && s.media.mime?.startsWith('video/')" :src="`/studio/media/${s.media.id}/file`" controls class="max-h-80 rounded-xl" />
          <pre v-else-if="s.body" class="whitespace-pre-wrap font-[inherit] leading-8">{{ s.body }}</pre>
          <p v-else class="text-subtle">{{ $t('ما انولّد لسا.') }}</p>
        </div>
        <div class="flex flex-col gap-3">
          <p class="text-sm leading-7"><b>{{ $t('المطلوب:') }}</b> {{ s.prompt }}</p>
          <button type="button" :disabled="working === s.id || s.status === 'generating'" class="min-h-[48px] rounded-xl bg-apricot font-bold text-ink disabled:opacity-50" @click="generate(s)">{{ working === s.id ? $t('بيشتغل…') : $t('ولّد المشهد ({p0} رصيد)', { p0: COST[s.type] }) }}</button>
          <button type="button" class="min-h-[40px] rounded-xl border border-stone text-sm font-bold" @click="router.delete(`/scenarios/${scenario.id}/scenes/${s.id}`, { preserveScroll: true })">{{ $t('احذف المشهد') }}</button>
        </div>
      </section>
    </template>

    <form class="grid gap-3 rounded-3xl border-2 border-dashed border-lavender p-6 sm:grid-cols-[1fr_1fr_2fr_auto]" @submit.prevent="add.post(`/scenarios/${scenario.id}/scenes`, { preserveScroll: true, onSuccess: () => add.reset('title', 'prompt') })">
      <select v-model="add.type" :aria-label="$t('نوع المشهد')" class="min-h-[44px] rounded-xl border border-stone bg-white px-3"><option v-for="tp in sceneTypes" :key="tp" :value="tp">{{ LABEL[tp] }}</option></select>
      <input v-model="add.title" required :placeholder="$t('عنوان المشهد')" :aria-label="$t('عنوان المشهد')" class="min-h-[44px] rounded-xl border border-stone px-3" />
      <input v-model="add.prompt" required :placeholder="$t('شو بدك يصير بالمشهد؟')" :aria-label="$t('وصف المشهد')" class="min-h-[44px] rounded-xl border border-stone px-3" />
      <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-5 font-bold text-white">{{ $t('أضف') }}</button>
    </form>
  </StudioShell>
</template>
