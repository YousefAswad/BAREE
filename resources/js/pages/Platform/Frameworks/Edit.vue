<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, postJson, t } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  framework: { version: number; name: Record<string, string> };
  competencies: { key: string; name: Record<string, string>; weight: number }[];
  levels: { rank: number; key: string; name: Record<string, string>; min_score: number; color: string; requirements: Record<string, unknown> | null }[];
}>();

const weights = reactive(Object.fromEntries(props.competencies.map((c) => [c.key, Number(c.weight)])));
const levels = reactive(props.levels.map((l) => ({ rank: l.rank, min_score: Number(l.min_score), requirements: l.requirements })));
const total = computed(() => Object.values(weights).reduce((a, b) => a + b, 0));
const preview = ref<{ current: Record<string, number>; proposed: Record<string, number>; sample: number } | null>(null);
const error = ref('');
let timer: ReturnType<typeof setTimeout> | undefined;

watch([weights, levels], () => {
  clearTimeout(timer);
  timer = setTimeout(async () => {
    try { preview.value = await postJson('/platform/framework/preview', { weights, levels }); error.value = ''; }
    catch (e) { error.value = (e as Error).message; }
  }, 400);
}, { deep: true, immediate: true });

const form = useForm({ reason: '' });
const save = () => form.transform((d) => ({ ...d, weights, levels })).put('/platform/framework', { preserveScroll: true });
</script>

<template>
  <AdminShell portal="platform" active="framework" :title="t(framework.name)" :subtitle="$t('الإصدار {p0}. شوف أثر أي تغيير على المستخدمين الحاليين قبل ما تحفظ.', { p0: ar(framework.version) })">
    <div class="grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('أوزان الكفاءات')">
        <template #actions><span class="rounded-full bg-mist px-3 py-1 text-sm font-extrabold text-indigo-deep">{{ $t('المجموع {p0}', { p0: ar(total) }) }}</span></template>
        <div v-for="c in competencies" :key="c.key" class="grid grid-cols-[1fr_2fr_auto] items-center gap-3">
          <label :for="`w-${c.key}`" class="text-sm font-bold">{{ t(c.name) }}</label>
          <input :id="`w-${c.key}`" v-model.number="weights[c.key]" type="range" min="0" max="30" step="1" class="accent-indigo" />
          <span class="w-10 text-left text-sm font-bold">{{ ar(weights[c.key]) }}</span>
        </div>
      </Panel>
      <Panel :title="$t('حدود المستويات وأثرها')">
        <div v-for="(l, i) in levels" :key="l.rank" class="grid grid-cols-[1fr_auto_auto] items-center gap-3 border-b border-[#F0EDE9] py-2">
          <span class="flex items-center gap-2 text-sm font-bold"><span class="h-4 w-4 rounded" :style="{ background: props.levels[i].color }" />{{ t(props.levels[i].name) }}</span>
          <label class="flex items-center gap-1 text-sm">{{ $t('من') }}<input v-model.number="l.min_score" type="number" min="0" max="100" :disabled="i === 0" class="min-h-[40px] w-20 rounded-lg border border-stone px-2" :aria-label="$t('بداية {p0}', { p0: t(props.levels[i].name) })" /></label>
          <span class="w-36 text-left text-sm">
            <template v-if="preview">{{ ar(preview.proposed[l.rank] ?? 0) }} <span class="text-subtle">{{ $t('(الآن {p0})', { p0: ar(preview.current[l.rank] ?? 0) }) }}</span></template>
          </span>
        </div>
        <p v-if="error" role="alert" class="text-sm text-[#9B1C1C]">{{ error }}</p>
        <p v-if="preview" class="text-xs text-subtle">{{ $t('محسوب على {p0} جواز حالي على كل المؤسسات.', { p0: ar(preview.sample) }) }}</p>
      </Panel>
    </div>
    <Panel :title="$t('الحفظ')" class="mt-6">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="save">
        <label class="flex flex-1 flex-col gap-1 text-sm font-bold">{{ $t('سبب التغيير (للتدقيق)') }}<input v-model="form.reason" required minlength="5" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <button type="submit" :disabled="!!error || form.processing" class="min-h-[44px] rounded-xl bg-ink px-6 font-bold text-white">{{ $t('احفظ كإصدار جديد') }}</button>
      </form>
    </Panel>
  </AdminShell>
</template>
