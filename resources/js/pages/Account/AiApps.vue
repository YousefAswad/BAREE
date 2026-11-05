<script setup lang="ts">
import StudioShell from '@/components/studio/StudioShell.vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{ tokens: { id: number; name: string; last_used_at: string | null; expires_at: string | null }[]; endpoint: string }>();
const form = useForm({ name: 'Claude Desktop' });
</script>

<template>
  <StudioShell :title="$t('ربط تطبيقات الذكاء الاصطناعي')">
    <h1 class="font-display text-3xl font-extrabold">{{ $t('استخدم BAREE من تطبيق AI تبعك') }}</h1>
    <p class="max-w-3xl leading-8 text-subtle">{{ $t('عن طريق MCP، بتقدر تستخدم أدوات BAREE ومركز المعرفة وجوازك من تطبيقات زي Claude. كل شي بيمشي بصلاحياتك وقواعد مؤسستك ورصيدها، والأسماء بتنحجب متل العادة.') }}</p>
    <section class="rounded-3xl border border-stone bg-white p-6">
      <p class="text-sm font-bold">{{ $t('عنوان الخادم') }}</p><code dir="ltr" class="mt-1 block rounded-xl bg-sand p-3 text-sm">{{ endpoint }}</code>
      <form class="mt-4 flex flex-wrap gap-2" @submit.prevent="form.post('/settings/ai-apps', { preserveScroll: true })">
        <label for="tn" class="sr-only">{{ $t('اسم الرمز') }}</label>
        <input id="tn" v-model="form.name" required class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-5 font-bold text-white">{{ $t('أنشئ رمز وصول (٩٠ يوم)') }}</button>
      </form>
      <p class="mt-2 text-xs text-subtle">{{ $t('الرمز بيظهر مرة وحدة بس. انسخه فورًا.') }}</p>
      <ul class="mt-4 flex flex-col gap-2 text-sm"><li v-for="tk in tokens" :key="tk.id" class="flex items-center justify-between rounded-xl bg-sand px-3 py-2">
        <span>{{ tk.name }}<span class="block text-xs text-subtle">{{ $t('آخر استخدام: {p0}', { p0: tk.last_used_at ? new Date(tk.last_used_at).toLocaleDateString('ar') : $t('ما استُخدم') }) }}</span></span>
        <button type="button" class="min-h-[36px] rounded-lg border border-stone px-3 text-xs font-bold" @click="router.delete(`/settings/ai-apps/${tk.id}`, { preserveScroll: true })">{{ $t('إلغاء') }}</button></li></ul>
    </section>
  </StudioShell>
</template>
