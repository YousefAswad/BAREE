<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { useLocale } from '@/composables/useLocale';
import { postJson, t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
  posts: { id: number; kind: string; title: Record<string, string>; body: Record<string, string> | null; cta_url: string | null; source: string; pinned: boolean; new: boolean }[];
  due: { course: string; title: Record<string, string>; due_at: string | null; progress: number }[];
  suggestion: { key: string; name: Record<string, string>; reason: string[] } | null;
  passport: { readiness: number; level: Record<string, string> | null; next: { gap: number } | null } | null;
}>();
const { t: $t } = useI18n();
const { n, date } = useLocale();
const KIND: Record<string, string> = { article: $t('مقال'), tip: $t('نصيحة'), video: $t('فيديو'), news: $t('خبر'), tool: $t('أداة جديدة'), story: $t('قصة نجاح'), event: $t('فعالية'), policy: $t('سياسة') };

async function open(post: { id: number; cta_url: string | null }) {
  const res = await postJson<{ url: string | null }>(`/posts/${post.id}/click`, {});
  if (res.url) window.location.href = res.url;
}
</script>

<template>
  <StudioShell :title="$t('الرئيسية')" active="home">
    <section v-if="passport" class="flex flex-wrap items-center justify-between gap-4 rounded-[28px] bg-ink p-8 text-white">
      <div><p class="text-sm font-bold text-apricot">{{ t(passport.level) }}</p>
        <p class="font-display mt-1 text-4xl font-extrabold">{{ $t('{p0}٪', { p0: n(Math.round(passport.readiness)) }) }}</p>
        <p v-if="passport.next" class="mt-1 text-sm text-[#CCCFE0]">{{ $t('باقي {n} نقاط للمستوى الجاي', { n: n(Math.ceil(passport.next.gap)) }) }}</p></div>
      <Link v-if="suggestion" :href="`/studio/tools/${suggestion.key}`" class="rounded-2xl bg-white/10 p-4 text-white no-underline">
        <span class="block text-xs font-bold text-apricot">{{ $t('مقترح إلك') }}</span><strong>{{ t(suggestion.name) }}</strong></Link>
    </section>

    <section v-if="due.length" class="rounded-3xl border border-[#F3B3B3] bg-white p-6">
      <h2 class="font-display mb-3 text-lg font-bold">{{ $t('مطلوب منك') }}</h2>
      <Link v-for="d in due" :key="d.course" :href="`/academy/${d.course}`" class="mb-2 flex justify-between rounded-xl bg-sand px-4 py-3 text-sm text-ink no-underline">
        <span class="font-bold">{{ t(d.title) }}</span><span class="text-[#9B1C1C]">{{ $t('{p0}: {p1} · {p2}٪', { p0: $t('آخر موعد'), p1: date(d.due_at), p2: n(d.progress) }) }}</span></Link>
    </section>

    <section class="grid gap-4 md:grid-cols-2" :aria-label="$t('الفيد')">
      <article v-for="p in posts" :key="p.id" class="flex flex-col gap-2 rounded-3xl border bg-white p-6" :class="p.pinned ? 'border-2 border-indigo' : 'border-stone'">
        <span class="flex flex-wrap items-center gap-2 text-xs font-bold">
          <span class="rounded-full bg-mist px-2 py-0.5 text-indigo-deep">{{ $t(KIND[p.kind] ?? p.kind) }}</span>
          <span class="text-subtle">{{ p.source === 'baree' ? 'BAREE' : $t('مؤسستك') }}</span>
          <span v-if="p.new" class="rounded-full bg-apricot px-2 py-0.5 text-ink">{{ $t('جديد') }}</span>
        </span>
        <h3 class="font-display text-lg font-bold">{{ t(p.title) }}</h3>
        <p class="leading-7 text-subtle">{{ t(p.body) }}</p>
        <button v-if="p.cta_url" type="button" class="mt-auto min-h-[44px] self-start rounded-xl bg-ink px-4 text-sm font-bold text-white" @click="open(p)">{{ $t('افتح') }}</button>
      </article>
      <p v-if="!posts.length" class="rounded-3xl bg-white p-8 text-center text-subtle md:col-span-2">{{ $t('ما في جديد هلّق.') }}</p>
    </section>
  </StudioShell>
</template>
