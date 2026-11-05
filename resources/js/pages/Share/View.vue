<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

defineProps<{ title: string; type: string; html: string | null; scenes: { type: string; title: string; html: string | null; media: string | null; mime: string | null }[] }>();
</script>

<template>
  <Head :title="title" />
  <main :dir="dir" class="mx-auto flex max-w-3xl flex-col gap-6 bg-sand px-6 py-10 text-ink">
    <h1 class="font-display text-3xl font-extrabold">{{ title }}</h1>
    <!-- html comes from CommonMark with raw HTML escaped and unsafe links disabled -->
    <article v-if="html" class="prose max-w-none leading-8" v-html="html" />
    <section v-for="(s, i) in scenes" :key="i" class="rounded-3xl border border-stone bg-white p-6">
      <h2 class="font-display mb-3 text-xl font-bold">{{ i + 1 }}. {{ s.title }}</h2>
      <img v-if="s.media && s.mime?.startsWith('image/')" :src="s.media" :alt="s.title" class="rounded-2xl" />
      <audio v-else-if="s.media && s.mime?.startsWith('audio/')" :src="s.media" controls class="w-full" />
      <video v-else-if="s.media && s.mime?.startsWith('video/')" :src="s.media" controls class="w-full rounded-2xl" />
      <div v-if="s.html" class="leading-8" v-html="s.html" />
    </section>
    <footer class="text-xs text-subtle">{{ $t('أُنشئ بمساعدة الذكاء الاصطناعي عبر') }} <span dir="ltr">BAREE</span>.</footer>
  </main>
</template>
