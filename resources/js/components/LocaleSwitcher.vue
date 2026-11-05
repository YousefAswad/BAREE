<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const { locale, switchTo } = useLocale();
</script>

<template>
  <button type="button" class="min-h-[40px] rounded-xl border border-stone bg-white px-3 text-sm font-bold text-ink" :lang="locale === 'ar' ? 'en' : 'ar'" @click="switchTo(locale === 'ar' ? 'en' : 'ar')">
    {{ locale === 'ar' ? 'English' : $t('العربية') }}
  </button>
</template>
