<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { useLocale } from '@/composables/useLocale';
import { t } from '@/lib/http';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{ notifications: { id: string; data: Record<string, any>; read_at: string | null; created_at: string }[]; unread: number; filter: string | null }>();
const { t: $t } = useI18n();
const { n, date } = useLocale();
const TABS = [[null, $t('الكل')], ['assignment', $t('المهمات')], ['achievement', $t('الإنجازات')], ['institution', $t('المؤسسة')], ['billing', $t('الفوترة')]];

function label(d: Record<string, any>): string {
  switch (d.type) {
    case 'assignment': return `${$t(d.kind === 'reminder' ? $t('تذكير') : d.kind === 'overdue' ? $t('متأخر') : $t('مهمة جديدة'))}: ${d.title ?? d.name ?? ''}`;
    case 'achievement': return `${$t('حصلت على شارة')}: ${t(d.name)}`;
    case 'campaign': return `${$t('تقييم جديد')}: ${d.name}`;
    case 'billing': return `${$t(d.overdue ? $t('فاتورة متأخرة') : $t('فاتورة جديدة'))}: ${d.number}`;
    case 'support_session': return $t('فريق دعم BAREE فتح جلسة دعم لمؤسستك');
    default: return d.title ?? '';
  }
}
</script>

<template>
  <StudioShell :title="$t('الإشعارات')">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="font-display text-3xl font-extrabold">{{ $t('الإشعارات') }}</h1>
      <div class="flex gap-2">
        <Link href="/settings/notifications" class="min-h-[44px] rounded-xl border border-stone bg-white px-4 py-2.5 text-sm font-bold">{{ $t('التفضيلات') }}</Link>
        <button type="button" class="min-h-[44px] rounded-xl bg-ink px-4 text-sm font-bold text-white" :disabled="!unread" @click="router.post('/notifications/read-all', {}, { preserveScroll: true })">{{ $t('علّم الكل كمقروء') }}</button>
      </div>
    </div>
    <nav class="flex flex-wrap gap-1" :aria-label="$t('تصفية')">
      <Link v-for="tab in TABS" :key="String(tab[0])" :href="tab[0] ? `/notifications?type=${tab[0]}` : '/notifications'" class="min-h-[38px] rounded-full border px-3 py-1.5 text-sm" :class="filter === tab[0] ? 'border-ink bg-ink font-bold text-white' : 'border-stone bg-white'">{{ $t(tab[1] as string) }}</Link>
    </nav>
    <ul class="flex flex-col gap-2">
      <li v-for="item in notifications" :key="item.id" class="flex items-center gap-4 rounded-2xl p-4" :class="item.read_at ? 'bg-white' : 'bg-[#F5F6FE]'">
        <span class="flex-1"><span class="block" :class="item.read_at ? '' : 'font-bold'">{{ label(item.data) }}</span><span class="text-xs text-subtle">{{ date(item.created_at) }}</span></span>
        <Link v-if="item.data.url" :href="item.data.url" class="text-sm font-bold text-indigo" @click="router.post(`/notifications/${item.id}/read`, {}, { preserveScroll: true })">{{ $t('افتح') }}</Link>
        <span v-if="!item.read_at" class="h-2.5 w-2.5 rounded-full bg-coral" :aria-label="$t('غير مقروء')" />
      </li>
      <li v-if="!notifications.length" class="rounded-2xl bg-white p-8 text-center text-subtle">{{ $t('ما في إشعارات.') }}</li>
    </ul>
    <p class="text-xs text-subtle">{{ $t('{n} غير مقروءة', { n: n(unread) }) }}</p>
  </StudioShell>
</template>
