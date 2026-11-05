<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

const props = defineProps<{ template: { key: string; name: Record<string, string>; time_limit_minutes: number | null }; questions: number; privacyMode: string }>();
const WHO: Record<string, string> = {
  aggregate: $t('التفاصيل إلك أنت بس. الإدارة بتشوف أرقام مجمّعة للأقسام بدون أسماء.'),
  consent: $t('التفاصيل إلك. مديرك بيشوف مستواك بس إذا وافقت من إعدادات ملفك.'),
  department_heads: $t('رئيس قسمك بيشوف مستواك العام، بدون تفاصيل إجاباتك.'),
};
</script>

<template>
  <Head :title="$t('تقييم الجاهزية')" />
  <main :dir="dir" class="mx-auto grid max-w-5xl gap-6 px-6 py-12 lg:grid-cols-[3fr_2fr]">
    <section class="flex flex-col gap-6 rounded-[28px] bg-ink p-10 text-white">
      <span class="text-sm font-bold text-apricot">{{ template.name.ar }}</span>
      <h1 class="font-display text-4xl font-extrabold leading-snug">{{ $t('مش امتحان.') }}<br />{{ $t('هو اكتشاف لوين أنت الآن.') }}</h1>
      <p class="leading-8 text-[#CCCFE0]">{{ $t('{p0} موقف من شغلك، حوالي {p1} دقيقة. بالآخر بتعرف نقاط قوتك ووين بدك تتطوّر، وبتاخد جوازك.', { p0: questions, p1: template.time_limit_minutes ?? 15 }) }}</p>
    </section>
    <aside class="flex flex-col gap-4">
      <div class="rounded-3xl border border-stone bg-white p-6">
        <h2 class="font-display text-lg font-bold">{{ $t('مين بيشوف نتيجتك؟') }}</h2>
        <p class="mt-2 leading-7">{{ WHO[privacyMode] ?? WHO.aggregate }}</p>
      </div>
      <button type="button" class="min-h-[56px] rounded-2xl bg-apricot text-lg font-bold text-ink" @click="router.post(`/assessments/${template.key}/start`)">{{ $t('ابدأ التقييم') }}</button>
    </aside>
  </main>
</template>
