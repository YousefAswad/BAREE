<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, t } from '@/lib/http';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ agents: { agent: string; name: Record<string, string>; ratings: number; average: number; edits: number; average_change: number; top_reasons: Record<string, number>; comments: string[] }[]; days: number }>();
const REASON: Record<string, string> = { too_long: $t('طويل'), too_short: $t('قصير'), inaccurate: $t('غير دقيق'), not_curriculum: $t('بعيد عن المنهاج'), not_local: $t('أمثلة غير محلية'), language: $t('اللغة'), format: $t('التنسيق'), great: $t('ممتاز') };
</script>

<template>
  <AdminShell portal="platform" active="feedback" :title="$t('ملاحظات المستخدمين')" :subtitle="$t('آخر {p0} يوم، من المستخدمين اللي وافقوا على تحسين الوكلاء فقط، وبدون أي أسماء.', { p0: ar(days) })">
    <div class="grid gap-4 lg:grid-cols-2">
      <Panel v-for="a in agents" :key="a.agent" :title="t(a.name)">
        <div class="grid grid-cols-3 gap-3 text-center">
          <div><p class="font-display text-2xl font-extrabold">{{ ar(a.average) }}</p><p class="text-xs text-subtle">{{ $t('متوسط التقييم') }}</p></div>
          <div><p class="font-display text-2xl font-extrabold">{{ ar(a.ratings) }}</p><p class="text-xs text-subtle">{{ $t('تقييم') }}</p></div>
          <div><p class="font-display text-2xl font-extrabold">{{ $t('{p0}٪', { p0: ar(Math.round(a.average_change)) }) }}</p><p class="text-xs text-subtle">{{ $t('متوسط التعديل اليدوي') }}</p></div>
        </div>
        <div class="flex flex-wrap gap-2"><button v-for="(n, r) in a.top_reasons" :key="r" type="button" class="min-h-[32px] rounded-full bg-[#FDF0E4] px-3 py-1 text-xs font-bold text-[#9A4A12]" :title="$t('حوّلها لحالة اختبار')" @click="router.post(`/platform/feedback/${a.agent}/convert`, { reason: r })">{{ REASON[r] ?? r }} · {{ ar(n) }} ↗</button></div>
        <ul class="flex flex-col gap-1 text-sm"><li v-for="(c, i) in a.comments" :key="i" class="rounded-xl bg-sand p-2">«{{ c }}»</li></ul>
      </Panel>
    </div>
  </AdminShell>
</template>
