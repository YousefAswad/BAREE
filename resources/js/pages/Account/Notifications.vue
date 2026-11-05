<script setup lang="ts">
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import StudioShell from '@/components/studio/StudioShell.vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ matrix: Record<string, Record<string, boolean>>; whatsapp: { available: boolean; linked: boolean; receiving: boolean; pending: boolean } }>();
const { t: $t } = useI18n();
const TYPES: Record<string, string> = { assignment: $t('مهمات ومواعيد'), achievement: $t('إنجازات ومستويات'), institution: $t('أخبار المؤسسة'), tools: $t('أدوات جديدة'), billing: $t('الفوترة'), security: $t('الأمان (دايمًا مفعّل)') };
const CHANNELS: Record<string, string> = { database: $t('بالمنصة'), mail: $t('بالبريد'), whatsapp: $t('واتساب') };
const phone = useForm({ phone: '' });
const code = useForm({ code: '' });
const set = (type: string, channel: string, enabled: boolean) => router.put('/settings/notifications', { type, channel, enabled }, { preserveScroll: true });
</script>

<template>
  <StudioShell :title="$t('تفضيلات الإشعارات')">
    <h1 class="font-display text-3xl font-extrabold">{{ $t('تفضيلات الإشعارات') }}</h1>
    <section class="rounded-3xl border border-stone bg-white p-6">
      <div v-for="(channels, type) in matrix" :key="type" class="flex flex-wrap items-center justify-between gap-4 border-b border-[#F0EDE9] py-3 last:border-0">
        <span class="font-bold">{{ $t(TYPES[type] ?? type) }}</span>
        <span class="flex flex-wrap gap-4">
          <label v-for="(enabled, channel) in channels" :key="channel" class="flex items-center gap-2 text-sm">{{ $t(CHANNELS[channel]) }}
            <ToggleSwitch :model-value="enabled" :label="`${$t(TYPES[type] ?? type)} ${$t(CHANNELS[channel])}`" @update:model-value="(v) => type !== 'security' && set(type, channel, v)" /></label>
        </span>
      </div>
    </section>
    <section v-if="props.whatsapp.available" class="rounded-3xl bg-ink p-6 text-white">
      <h2 class="font-display mb-2 text-lg font-bold">{{ $t('واتساب') }}</h2>
      <p class="mb-3 text-sm text-[#CCCFE0]">{{ $t('تذكيرات المهمات، واسأل مركز المعرفة من واتساب. ابعت «إيقاف» بأي وقت لإيقاف الرسائل.') }}</p>
      <template v-if="props.whatsapp.receiving">
        <p class="text-sm font-bold text-apricot">{{ $t('واتساب متصل.') }}</p>
        <button type="button" class="mt-2 min-h-[40px] rounded-xl border border-white/30 px-4 text-sm" @click="router.delete('/settings/whatsapp', { preserveScroll: true })">{{ $t('إيقاف') }}</button>
      </template>
      <form v-else-if="!props.whatsapp.pending" class="flex flex-wrap gap-2" @submit.prevent="phone.post('/settings/whatsapp', { preserveScroll: true })">
        <label for="ph" class="sr-only">{{ $t('رقم الهاتف') }}</label>
        <input id="ph" v-model="phone.phone" dir="ltr" required placeholder="+970599123456" class="min-h-[44px] flex-1 rounded-xl border border-white/20 bg-transparent px-3" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-apricot px-4 font-bold text-ink">{{ $t('أرسل الرمز') }}</button>
      </form>
      <form v-else class="flex flex-wrap gap-2" @submit.prevent="code.post('/settings/whatsapp/verify', { preserveScroll: true })">
        <label for="otp" class="sr-only">{{ $t('الرمز') }}</label>
        <input id="otp" v-model="code.code" inputmode="numeric" maxlength="6" required autocomplete="one-time-code" class="min-h-[44px] w-40 rounded-xl border border-white/20 bg-transparent px-3 text-center tracking-widest" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-apricot px-4 font-bold text-ink">{{ $t('تأكيد') }}</button>
      </form>
    </section>
  </StudioShell>
</template>
