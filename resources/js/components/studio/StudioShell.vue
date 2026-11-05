<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ title: string; active?: string }>();
const { t: $t } = useI18n();
const { dir } = useLocale();
const page = usePage();
const flash = computed(() => (page.props.flash as { status?: string } | undefined)?.status);
const errors = computed(() => Object.values((page.props.errors ?? {}) as Record<string, string>));
const otherAreas = computed(() => ((page.props.areas ?? []) as { key: string; label: string; href: string }[]).filter((a) => a.key !== 'home'));
const NAV = [['home', $t('الرئيسية'), '/home'], ['studio', $t('الاستوديو'), '/studio'], ['workspaces', $t('مساحات العمل'), '/workspaces'], ['lab', $t('المختبر'), '/lab'], ['academy', $t('الأكاديمية'), '/academy'], ['hub', $t('مركز المعرفة'), '/hub'], ['passport', $t('جوازي'), '/passport']];
</script>

<template>
  <Head :title="props.title" />
  <div :dir="dir" class="min-h-screen bg-sand text-ink">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-stone bg-white px-6 py-3">
      <span dir="ltr" class="font-display text-xl font-extrabold tracking-wider">BAREE</span>
      <nav :aria-label="$t('القائمة')" class="flex flex-wrap gap-1">
        <Link v-for="n in NAV" :key="n[0]" :href="n[2]" class="min-h-[40px] rounded-xl px-4 py-2 text-sm font-bold" :class="n[0] === props.active ? 'bg-ink text-white' : 'text-[#3B3F57]'" :aria-current="n[0] === props.active ? 'page' : undefined">{{ $t(n[1]) }}</Link>
      </nav>
      <div class="flex items-center gap-2">
        <Link href="/notifications" class="min-h-[40px] rounded-xl border border-stone px-3 py-2 text-sm font-bold">{{ $t('الإشعارات') }}</Link>
        <Link href="/settings/privacy" class="min-h-[40px] rounded-xl border border-stone px-3 py-2 text-sm font-bold">{{ $t('الخصوصية') }}</Link>
        <Link v-for="a in otherAreas" :key="a.key" :href="a.href" class="min-h-[40px] rounded-xl bg-ink px-3 py-2 text-sm font-bold text-white no-underline">{{ $t(a.label) }}</Link>
        <LocaleSwitcher />
      </div>
    </header>
    <main class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-8">
      <p v-if="flash" role="status" class="break-all rounded-2xl bg-mist p-4 text-sm font-bold text-indigo-deep">{{ flash }}</p>
      <ul v-if="errors.length" role="alert" class="rounded-2xl bg-[#FBE3E3] p-4 text-sm text-[#9B1C1C]"><li v-for="e in errors" :key="e">{{ e }}</li></ul>
      <slot />
    </main>
  </div>
</template>
