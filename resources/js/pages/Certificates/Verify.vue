<script setup lang="ts">
import { ar, t } from '@/lib/http';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/composables/useLocale';

const { t: $t } = useI18n();
const { dir } = useLocale();

defineProps<{ serial: string; result: { valid: boolean; holder: string; course: Record<string, string> | null; hours: number; issued_at: string; revoked: boolean } | null }>();
</script>

<template>
  <Head :title="$t('التحقّق من شهادة')" />
  <main :dir="dir" class="flex min-h-screen items-center justify-center bg-sand p-6 text-ink">
    <section class="w-full max-w-md rounded-3xl border border-stone bg-white p-8 text-center">
      <p dir="ltr" class="font-display text-xl font-extrabold tracking-wider">BAREE</p>
      <template v-if="result">
        <p class="mt-4 inline-block rounded-full px-4 py-1 text-sm font-extrabold" :class="result.valid ? 'bg-mist text-indigo-deep' : 'bg-[#FBE3E3] text-[#9B1C1C]'">{{ result.valid ? $t('شهادة صحيحة') : result.revoked ? $t('شهادة ملغاة') : $t('بيانات الشهادة معدّلة') }}</p>
        <h1 class="font-display mt-4 text-2xl font-extrabold">{{ result.holder }}</h1>
        <p class="mt-1">{{ t(result.course) }}</p>
        <p class="mt-1 text-sm text-subtle">{{ $t('{p0} ساعة · {p1}', { p0: ar(result.hours), p1: result.issued_at }) }}</p>
      </template>
      <p v-else class="mt-6 text-subtle">{{ $t('ما في شهادة بهالرقم.') }}</p>
      <p dir="ltr" class="mt-6 text-xs text-subtle">{{ serial }}</p>
    </section>
  </main>
</template>
