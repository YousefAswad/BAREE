<script setup lang="ts">
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { useLocale } from '@/composables/useLocale';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ portal: 'platform' | 'institution'; active: string; title: string; subtitle?: string }>();
const page = usePage();
const { t: $t } = useI18n();
const { dir } = useLocale();
const flash = computed(() => (page.props.flash as { status?: string } | undefined)?.status);
const errors = computed(() => Object.values((page.props.errors ?? {}) as Record<string, string>));

const NAV = {
  platform: [
    { key: 'dashboard', label: $t('لوحة المنصة'), href: '/platform' },
    { key: 'tenants', label: $t('المؤسسات'), href: '/platform/tenants' },
    { key: 'agents', label: $t('مدير الوكلاء'), href: '/platform/agents' },
    { key: 'providers', label: $t('مزوّدو AI'), href: '/platform/providers' },
    { key: 'framework', label: $t('الكفاءات والمستويات'), href: '/platform/framework' },
    { key: 'prompts', label: $t('مكتبة البرومبتات'), href: '/platform/prompts' },
    { key: 'questions', label: $t('بنك الأسئلة'), href: '/platform/questions' },
    { key: 'learning', label: $t('المحتوى التعليمي'), href: '/platform/learning' },
    { key: 'plans', label: $t('الباقات'), href: '/platform/plans' },
    { key: 'feedback', label: $t('ملاحظات المستخدمين'), href: '/platform/feedback' },
    { key: 'content', label: $t('المحتوى العام'), href: '/platform/content' },
    { key: 'billing', label: $t('الفوترة'), href: '/platform/billing' },
    { key: 'audit', label: $t('سجل التدقيق'), href: '/platform/audit' },
  ],
  institution: [
    { key: 'dashboard', label: $t('لوحة المؤسسة'), href: '/admin' },
    { key: 'users', label: $t('المستخدمين'), href: '/admin/users' },
    { key: 'structure', label: $t('الهيكل والأقسام'), href: '/admin/structure' },
    { key: 'roles', label: $t('الأدوار والصلاحيات'), href: '/admin/roles' },
    { key: 'segments', label: $t('الفئات'), href: '/admin/segments' },
    { key: 'campaigns', label: $t('حملات التقييم'), href: '/admin/campaigns' },
    { key: 'agents', label: $t('الوكلاء والقواعد'), href: '/admin/agents' },
    { key: 'knowledge', label: $t('وثائق المؤسسة'), href: '/admin/knowledge' },
    { key: 'content', label: $t('المحتوى والإعلانات'), href: '/admin/content' },
    { key: 'assignments', label: $t('التعيينات الإلزامية'), href: '/admin/assignments' },
    { key: 'analytics', label: $t('الاستخدام والأثر'), href: '/admin/analytics' },
    { key: 'billing', label: $t('الاشتراك والفواتير'), href: '/admin/billing' },
    { key: 'privacy', label: $t('الخصوصية'), href: '/admin/privacy' },
  ],
};
const items = computed(() => NAV[props.portal]);
const otherAreas = computed(() => ((page.props.areas ?? []) as { key: string; label: string; href: string }[]).filter((a) => a.key !== (props.portal === 'platform' ? 'platform' : 'admin')));
const dark = computed(() => props.portal === 'platform');
</script>

<template>
  <Head :title="props.title" />
  <div :dir="dir" class="flex min-h-screen flex-wrap bg-sand text-ink">
    <aside class="w-full shrink-0 p-4 md:sticky md:top-0 md:h-screen md:w-72 md:overflow-y-auto md:p-5" :class="dark ? 'bg-ink text-white' : 'border-stone bg-white md:border-e'">
      <div class="mb-6 flex items-baseline gap-2 px-2">
        <span dir="ltr" class="font-display text-2xl font-extrabold tracking-wider">BAREE</span>
        <span class="text-xs font-bold" :class="dark ? 'text-apricot' : 'text-indigo-deep'">{{ dark ? 'Super Admin' : $t('إدارة المؤسسة') }}</span>
      </div>
      <nav :aria-label="$t('القائمة')" class="flex flex-row flex-nowrap gap-1 overflow-x-auto pb-1 md:flex-col md:overflow-visible md:pb-0">
        <Link
          v-for="item in items"
          :key="item.key"
          :href="item.href"
          class="flex min-h-[44px] shrink-0 items-center whitespace-nowrap rounded-xl border-s-4 px-3 text-sm md:whitespace-normal"
          :class="item.key === props.active ? (dark ? 'border-apricot bg-white/10 font-extrabold text-white' : 'border-indigo bg-mist font-extrabold text-indigo-deep') : (dark ? 'border-transparent font-medium text-[#D5D8EC] hover:bg-white/10' : 'border-transparent font-medium text-[#33364D] hover:bg-sand')"
          :aria-current="item.key === props.active ? 'page' : undefined"
        >{{ $t(item.label) }}</Link>
      </nav>
      <nav v-if="otherAreas.length" :aria-label="$t('القائمة')" class="mt-6 flex flex-row flex-wrap gap-1 border-t pt-4 md:flex-col" :class="dark ? 'border-white/10' : 'border-stone'">
        <Link v-for="a in otherAreas" :key="a.key" :href="a.href" class="flex min-h-[42px] items-center rounded-xl px-3 text-sm font-bold" :class="dark ? 'text-apricot hover:bg-white/10' : 'text-indigo hover:bg-sand'">{{ $t(a.label) }}</Link>
      </nav>
    </aside>
    <main class="min-w-0 flex-1 p-4 sm:p-6 md:p-10">
      <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 class="font-display text-2xl font-extrabold text-ink md:text-3xl">{{ props.title }}</h1>
          <p v-if="props.subtitle" class="mt-2 max-w-3xl text-sm leading-7 text-subtle md:text-base">{{ props.subtitle }}</p>
        </div>
        <div class="flex flex-wrap gap-2"><slot name="actions" /><LocaleSwitcher /></div>
      </header>
      <p v-if="flash" role="status" class="mb-4 rounded-2xl bg-mist p-4 text-sm font-bold text-indigo-deep">{{ flash }}</p>
      <ul v-if="errors.length" role="alert" class="mb-4 rounded-2xl bg-[#FBE3E3] p-4 text-sm text-[#9B1C1C]">
        <li v-for="e in errors" :key="e">{{ e }}</li>
      </ul>
      <slot />
    </main>
  </div>
</template>
