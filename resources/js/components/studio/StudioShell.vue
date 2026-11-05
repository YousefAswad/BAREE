<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import LogoutButton from '@/components/LogoutButton.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ title: string; active?: string }>();
const { t: $t } = useI18n();
const { dir } = useLocale();
const page = usePage();
const flash = computed(() => (page.props.flash as { status?: string } | undefined)?.status);
const errors = computed(() => Object.values((page.props.errors ?? {}) as Record<string, string>));
const otherAreas = computed(() => ((page.props.areas ?? []) as { key: string; label: string; href: string }[]).filter((a) => a.key !== 'home'));
const user = computed(() => (page.props.auth as { user?: { name?: string } } | undefined)?.user);
const NAV = [
  ['home', $t('الرئيسية'), '/home'], ['studio', $t('الاستوديو'), '/studio'], ['workspaces', $t('مساحات العمل'), '/workspaces'],
  ['lab', $t('المختبر'), '/lab'], ['academy', $t('الأكاديمية'), '/academy'], ['hub', $t('مركز المعرفة'), '/hub'], ['passport', $t('جوازي'), '/passport'],
];

// Mobile drawer: closes on navigation, Escape, and backdrop click; locks body scroll while open.
const open = ref(false);
const toggle = ref<HTMLButtonElement | null>(null);
const closeBtn = ref<HTMLButtonElement | null>(null);
watch(open, async (v) => {
  document.body.style.overflow = v ? 'hidden' : '';
  if (v) { await nextTick(); closeBtn.value?.focus(); } else toggle.value?.focus({ preventScroll: true });
});
const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape' && open.value) open.value = false; };
let off: (() => void) | undefined;
onMounted(() => { document.addEventListener('keydown', onKey); off = router.on('navigate', () => { open.value = false; }); });
onBeforeUnmount(() => { document.removeEventListener('keydown', onKey); document.body.style.overflow = ''; off?.(); });
</script>

<template>
  <Head :title="props.title" />
  <div :dir="dir" class="min-h-screen overflow-x-clip bg-sand text-ink">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-lg focus:bg-white focus:p-3 focus:font-bold">{{ $t('تخطّى إلى المحتوى') }}</a>
    <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-stone bg-white/95 px-4 py-2 backdrop-blur md:px-6">
      <div class="flex items-center gap-3">
        <button ref="toggle" type="button" class="inline-flex size-11 items-center justify-center rounded-xl border border-stone xl:hidden" :aria-label="$t('القائمة')" :aria-expanded="open" aria-controls="studio-drawer" @click="open = true"><Menu class="size-5" aria-hidden="true" /></button>
        <Link href="/home" dir="ltr" class="font-display text-xl font-extrabold tracking-wider text-ink no-underline">BAREE</Link>
      </div>
      <nav :aria-label="$t('القائمة')" class="hidden flex-nowrap gap-1 xl:flex">
        <Link v-for="n in NAV" :key="n[0]" :href="n[2]" class="min-h-[40px] whitespace-nowrap rounded-xl px-3 py-2 text-sm font-bold transition-colors" :class="n[0] === props.active ? 'bg-ink text-white' : 'text-[#3B3F57] hover:bg-sand'" :aria-current="n[0] === props.active ? 'page' : undefined">{{ $t(n[1]) }}</Link>
      </nav>
      <div class="flex items-center gap-2">
        <Link href="/notifications" class="hidden min-h-[40px] rounded-xl border border-stone px-3 py-2 text-sm font-bold hover:bg-sand sm:inline-flex">{{ $t('الإشعارات') }}</Link>
        <Link href="/settings/privacy" class="hidden min-h-[40px] rounded-xl border border-stone px-3 py-2 text-sm font-bold hover:bg-sand xl:inline-flex">{{ $t('الخصوصية') }}</Link>
        <Link v-for="a in otherAreas" :key="a.key" :href="a.href" class="hidden min-h-[40px] items-center rounded-xl bg-ink px-3 py-2 text-sm font-bold text-white no-underline hover:bg-indigo-deep md:inline-flex">{{ $t(a.label) }}</Link>
        <LocaleSwitcher />
        <span class="hidden sm:block"><LogoutButton /></span>
      </div>
    </header>

    <!-- mobile / tablet drawer -->
    <div v-if="open" class="fixed inset-0 z-40 xl:hidden" role="dialog" aria-modal="true" :aria-label="$t('القائمة')">
      <div class="absolute inset-0 bg-ink/50" @click="open = false"></div>
      <aside id="studio-drawer" class="absolute inset-y-0 start-0 flex w-[min(20rem,88vw)] flex-col gap-1 overflow-y-auto bg-white p-4 shadow-xl">
        <div class="mb-3 flex items-center justify-between">
          <span dir="ltr" class="font-display text-xl font-extrabold tracking-wider">BAREE</span>
          <button ref="closeBtn" type="button" class="inline-flex size-11 items-center justify-center rounded-xl border border-stone" :aria-label="$t('إغلاق')" @click="open = false"><X class="size-5" aria-hidden="true" /></button>
        </div>
        <p v-if="user?.name" class="mb-2 truncate px-2 text-sm font-bold text-subtle">{{ user.name }}</p>
        <Link v-for="n in NAV" :key="n[0]" :href="n[2]" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-bold" :class="n[0] === props.active ? 'bg-ink text-white' : 'text-ink hover:bg-sand'" :aria-current="n[0] === props.active ? 'page' : undefined">{{ $t(n[1]) }}</Link>
        <div class="my-2 border-t border-stone"></div>
        <Link href="/notifications" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-bold hover:bg-sand">{{ $t('الإشعارات') }}</Link>
        <Link href="/settings/privacy" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-bold hover:bg-sand">{{ $t('الخصوصية') }}</Link>
        <Link v-for="a in otherAreas" :key="a.key" :href="a.href" class="flex min-h-[48px] items-center rounded-xl bg-mist px-4 text-base font-bold text-indigo-deep">{{ $t(a.label) }}</Link>
        <div class="mt-auto pt-4"><LogoutButton variant="solid" class="w-full" /></div>
      </aside>
    </div>

    <main id="main" class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 md:px-6 md:py-8">
      <p v-if="flash" role="status" class="break-words rounded-2xl bg-mist p-4 text-sm font-bold text-indigo-deep">{{ flash }}</p>
      <ul v-if="errors.length" role="alert" class="rounded-2xl bg-[#FBE3E3] p-4 text-sm text-[#9B1C1C]"><li v-for="e in errors" :key="e">{{ e }}</li></ul>
      <slot />
    </main>
  </div>
</template>
