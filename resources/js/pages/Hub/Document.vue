<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ document: { id: number; kind: string; title: Record<string, string>; body: string | null; version: number } }>();
</script>

<template>
  <StudioShell :title="t(document.title)" active="hub">
    <Link href="/hub" class="text-sm font-bold text-indigo">{{ $t('مركز المعرفة') }}</Link>
    <article class="rounded-3xl border border-stone bg-white p-8">
      <h1 class="font-display mb-4 text-3xl font-extrabold">{{ t(document.title) }}</h1>
      <pre class="whitespace-pre-wrap font-[inherit] leading-8">{{ document.body }}</pre>
      <p class="mt-6 text-xs text-subtle">{{ $t('الإصدار {p0}', { p0: document.version }) }}</p>
    </article>
  </StudioShell>
</template>
