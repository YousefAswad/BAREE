<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import { ar } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ mode: string; leaderboards: boolean; allowedDomains: string[]; protectedTermsCount: number; minGroup: number }>();
const settings = useForm({ mode: props.mode, leaderboards: props.leaderboards, allowed_email_domains: props.allowedDomains.join(', ') });
const terms = useForm({ terms: '', file: null as File | null });
const MODES = [
  ['aggregate', $t('مجمّعة فقط'), $t('الإدارة بتشوف أرقام الأقسام بس. أصدق النتائج لأن ما حدا خايف.')],
  ['consent', $t('فردية بموافقة الموظف'), $t('كل موظف بيقرر إذا مديره بيشوف مستواه.')],
  ['department_heads', $t('رؤساء الأقسام لأقسامهم'), $t('رئيس القسم بيشوف مستويات فريقه بدون تفاصيل الإجابات.')],
];
</script>

<template>
  <AdminShell portal="institution" active="privacy" :title="$t('الخصوصية')" :subtitle="$t('أي مجموعة أقل من {p0} أشخاص بتنخفى من كل التقارير تلقائيًا.', { p0: ar(minGroup) })">
    <div class="grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('مين بيشوف نتائج الموظفين')">
        <form class="flex flex-col gap-3" @submit.prevent="settings.transform((d) => ({ ...d, allowed_email_domains: d.allowed_email_domains.split(',').map((s) => s.trim()).filter(Boolean) })).put('/admin/privacy', { preserveScroll: true })">
          <label v-for="m in MODES" :key="m[0]" class="flex gap-3 rounded-2xl border p-4" :class="settings.mode === m[0] ? 'border-2 border-indigo bg-mist' : 'border-stone'">
            <input v-model="settings.mode" type="radio" :value="m[0]" class="mt-1 accent-indigo" />
            <span><b class="block text-sm">{{ m[1] }}</b><span class="text-sm text-subtle">{{ m[2] }}</span></span>
          </label>
          <div class="flex items-center justify-between rounded-2xl border border-stone p-4 text-sm font-bold">{{ $t('لوحات المتصدّرين') }}<ToggleSwitch v-model="settings.leaderboards" :label="$t('لوحات المتصدّرين')" /></div>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('نطاقات البريد المسموحة للدعوات') }}<input v-model="settings.allowed_email_domains" dir="ltr" placeholder="school.ps, school.edu" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('حفظ') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('أسماء محمية من الذكاء الاصطناعي')" dark>
        <p class="text-sm leading-7 text-[#DEE0EE]">{{ $t('أسماء الطلاب هون ما بتوصل لأي مزوّد أبدًا: بتتحوّل لرموز قبل الإرسال، وبترجع بالنتيجة لصاحبها بس. محفوظة مشفّرة وما بتظهر هون.') }}</p>
        <p class="font-display text-4xl font-extrabold text-apricot">{{ $t('{p0} اسم', { p0: ar(protectedTermsCount) }) }}</p>
        <form class="flex flex-col gap-3" @submit.prevent="terms.post('/admin/privacy/terms', { preserveScroll: true, forceFormData: true, onSuccess: () => terms.reset() })">
          <label class="text-sm">{{ $t('أضف أسماء (سطر لكل اسم)') }}<textarea v-model="terms.terms" rows="4" class="mt-1 w-full rounded-xl border border-white/20 bg-transparent p-3" /></label>
          <label class="text-sm">{{ $t('أو ارفع قائمة الطلاب (CSV)') }}<input type="file" accept=".csv,.txt" class="mt-1 block text-sm" @change="terms.file = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label>
          <button type="submit" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink">{{ $t('أضف للحماية') }}</button>
        </form>
      </Panel>
    </div>
  </AdminShell>
</template>
