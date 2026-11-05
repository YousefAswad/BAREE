<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { postJson, t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ resources: { id: number; kind: string; title: Record<string, string>; source: string; excerpt: string }[] }>();
const KIND: Record<string, string> = { all: $t('الكل'), guide: $t('أدلة'), prompt_template: $t('قوالب طلبات'), policy: $t('سياسات مؤسستي'), research: $t('أبحاث'), video: $t('فيديو') };
const kind = ref('all');
const question = ref('');
const answer = ref<{ answer: string; answered: boolean; citations: { n: number; title: Record<string, string>; excerpt: string }[] } | null>(null);
const busy = ref(false);
const error = ref('');
const shown = computed(() => props.resources.filter((r) => kind.value === 'all' || r.kind === kind.value));

async function ask() {
  busy.value = true; error.value = ''; answer.value = null;
  try { answer.value = await postJson('/hub/ask', { question: question.value }); }
  catch (e) { error.value = (e as Error).message; }
  finally { busy.value = false; }
}
</script>

<template>
  <StudioShell :title="$t('مركز المعرفة')" active="hub">
    <section class="rounded-[28px] bg-ink p-6 text-white md:p-8">
      <label for="hq" class="text-sm font-bold text-apricot">{{ $t('اسأل عن سياسات مؤسستك وأدلتها') }}</label>
      <form class="mt-3 flex flex-wrap gap-2" @submit.prevent="ask">
        <input id="hq" v-model="question" required minlength="5" :placeholder="$t('هل بقدر أستخدم أدوات AI مع بيانات الطلاب؟')" class="min-h-[52px] flex-1 rounded-2xl border border-white/20 bg-white/10 px-4 text-white placeholder:text-[#B8BBD4]" />
        <button type="submit" :disabled="busy" class="min-h-[52px] rounded-2xl bg-apricot px-6 font-bold text-ink">{{ busy ? '…' : $t('اسأل') }}</button>
      </form>
      <p v-if="error" role="alert" class="mt-3 text-sm text-[#FFB4B4]">{{ error }}</p>
      <div v-if="answer" class="mt-4 rounded-2xl bg-white p-5 text-ink" aria-live="polite">
        <pre class="whitespace-pre-wrap font-[inherit] leading-8">{{ answer.answer }}</pre>
        <ul v-if="answer.citations.length" class="mt-3 flex flex-col gap-2 border-t border-stone pt-3 text-sm">
          <li v-for="c in answer.citations" :key="c.n"><b>[{{ c.n }}] {{ t(c.title) }}</b><span class="block text-subtle">{{ c.excerpt }}…</span></li>
        </ul>
      </div>
    </section>
    <div role="group" :aria-label="$t('النوع')" class="flex flex-wrap gap-1">
      <button v-for="(label, k) in KIND" :key="k" type="button" :aria-pressed="kind === k" class="min-h-[38px] rounded-full border px-3 text-sm" :class="kind === k ? 'border-ink bg-ink font-bold text-white' : 'border-stone bg-white'" @click="kind = k">{{ label }}</button>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Link v-for="r in shown" :key="r.id" :href="`/hub/documents/${r.id}`" class="flex flex-col gap-2 rounded-3xl border border-stone bg-white p-5 text-ink no-underline hover:border-indigo">
        <span class="flex justify-between text-xs font-bold"><span class="rounded-full bg-mist px-2 py-0.5 text-indigo-deep">{{ KIND[r.kind] ?? r.kind }}</span><span class="text-subtle">{{ r.source === 'baree' ? 'BAREE' : $t('مؤسستك') }}</span></span>
        <strong class="font-display">{{ t(r.title) }}</strong>
        <span class="text-sm text-subtle">{{ r.excerpt }}</span>
      </Link>
    </div>
  </StudioShell>
</template>
