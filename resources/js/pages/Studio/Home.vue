<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Tool = { key: string; name: Record<string, string>; description: Record<string, string> | null; category: string; content_type: string; availability: string; human_review: boolean; reason?: string[] };
const props = defineProps<{ recommended: Tool[]; tools: Tool[]; recentWorkspaces: { id: number; title: string; kind: string }[] }>();

const TYPES: Record<string, string> = { all: $t('كل الأنواع'), text: $t('نصوص'), data: $t('بيانات'), slides: $t('عروض'), image: $t('صور'), video: $t('فيديو'), model3d: '3D', voice: $t('صوت'), interactive: $t('تفاعلي'), comms: $t('تواصل') };
const REASON: Record<string, string> = { data_analysis: $t('البيانات'), prompting: $t('كتابة الطلب'), content_creation: $t('صناعة المحتوى'), verification: $t('التحقّق'), responsible_ai: $t('الاستخدام المسؤول'), productivity: $t('الإنتاجية') };
const q = ref('');
const type = ref('all');
const filtered = computed(() => props.tools.filter((tool) => (type.value === 'all' || tool.content_type === type.value)
  && (!q.value || t(tool.name).includes(q.value) || t(tool.description).includes(q.value))));
</script>

<template>
  <StudioShell :title="$t('الاستوديو')" active="studio">
    <section class="rounded-[30px] p-8 text-white md:p-10" style="background: radial-gradient(circle at 85% -10%, rgba(104,120,214,.6), transparent 50%), #1E2238">
      <p class="text-sm font-bold text-apricot">{{ $t('استوديو AI') }}</p>
      <h1 class="font-display mt-2 text-3xl font-extrabold md:text-4xl">{{ $t('شو بدك تنجز اليوم؟') }}</h1>
      <label for="tq" class="sr-only">{{ $t('ابحث بالأدوات') }}</label>
      <input id="tq" v-model="q" type="search" :placeholder="$t('ابحث: خطة، ورقة عمل، رسالة، تقرير...')" class="mt-6 min-h-[56px] w-full max-w-2xl rounded-2xl border border-white/20 bg-white/10 px-5 text-lg text-white placeholder:text-[#B8BBD4]" />
    </section>

    <section v-if="recommended.length" aria-labelledby="rec">
      <h2 id="rec" class="font-display mb-3 text-xl font-bold">{{ $t('مقترح إلك') }}</h2>
      <div class="grid gap-4 md:grid-cols-3">
        <Link v-for="(tool, i) in recommended" :key="tool.key" :href="`/studio/tools/${tool.key}`" class="flex min-h-[170px] flex-col gap-2 rounded-3xl p-6 no-underline" :class="i === 0 ? 'bg-ink text-white' : 'border border-stone bg-white text-ink'">
          <span v-if="tool.reason?.length" class="text-xs font-extrabold" :class="i === 0 ? 'text-apricot' : 'text-[#9A4A12]'">{{ $t('بيقوّي {p0}', { p0: tool.reason.map((r) => REASON[r] ?? r).join($t('، ')) }) }}</span>
          <strong class="font-display text-xl">{{ t(tool.name) }}</strong>
          <span class="text-sm leading-7" :class="i === 0 ? 'text-[#CCCFE0]' : 'text-subtle'">{{ t(tool.description) }}</span>
        </Link>
      </div>
    </section>

    <section aria-labelledby="all">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 id="all" class="font-display text-xl font-bold">{{ $t('كل الأدوات المتاحة إلك') }}</h2>
        <div role="group" :aria-label="$t('نوع المحتوى')" class="flex flex-wrap gap-1">
          <button v-for="(label, key) in TYPES" :key="key" type="button" :aria-pressed="type === key" class="min-h-[38px] rounded-full border px-3 text-sm" :class="type === key ? 'border-ink bg-ink font-bold text-white' : 'border-stone bg-white'" @click="type = key">{{ label }}</button>
        </div>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <component :is="tool.availability === 'available' ? Link : 'div'" v-for="tool in filtered" :key="tool.key" :href="tool.availability === 'available' ? `/studio/tools/${tool.key}` : undefined"
          class="flex flex-col gap-2 rounded-2xl border border-stone bg-white p-4 text-ink no-underline" :class="tool.availability !== 'available' ? 'opacity-70' : 'hover:border-indigo'">
          <span class="flex items-center justify-between gap-2">
            <strong class="text-base">{{ t(tool.name) }}</strong>
            <span v-if="tool.availability !== 'available'" class="rounded-full bg-stone px-2 py-0.5 text-xs font-bold text-subtle">{{ $t('قريبًا') }}</span>
            <span v-else-if="tool.human_review" class="rounded-full bg-[#FDF0E4] px-2 py-0.5 text-xs font-bold text-[#9A4A12]">{{ $t('بإشراف بشري') }}</span>
          </span>
          <span class="text-sm leading-7 text-subtle">{{ t(tool.description) }}</span>
          <span class="text-xs text-subtle">{{ TYPES[tool.content_type] }}</span>
        </component>
      </div>
      <p v-if="!filtered.length" class="rounded-2xl bg-white p-8 text-center text-subtle">{{ $t('ما لقينا أداة بهالوصف.') }}</p>
    </section>
  </StudioShell>
</template>
