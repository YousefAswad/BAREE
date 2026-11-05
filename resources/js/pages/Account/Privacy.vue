<script setup lang="ts">
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import StudioShell from '@/components/studio/StudioShell.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ consents: Record<string, boolean>; mode: string; leaderboards: boolean }>();
const MODE: Record<string, string> = {
  aggregate: $t('مؤسستك بتشوف أرقام مجمّعة بس. مستواك الفردي إلك أنت.'),
  consent: $t('مديرك بيشوف مستواك بس إذا فعّلت الخيار تحت.'),
  department_heads: $t('رئيس قسمك بيشوف مستواك العام، بدون تفاصيل إجاباتك.'),
};
const set = (key: string, granted: boolean) => router.put('/settings/privacy', { key, granted }, { preserveScroll: true });
const confirmDelete = ref(false);
const del = useForm({ password: '' });
</script>

<template>
  <StudioShell :title="$t('الخصوصية والبيانات')">
    <h1 class="font-display text-3xl font-extrabold">{{ $t('الخصوصية والبيانات') }}</h1>
    <section class="rounded-3xl bg-ink p-6 text-white"><p class="text-sm font-bold text-apricot">{{ $t('سياسة مؤسستك') }}</p><p class="mt-1 leading-8">{{ MODE[mode] ?? MODE.aggregate }}</p></section>
    <section class="flex flex-col gap-1 rounded-3xl border border-stone bg-white p-6">
      <div v-if="mode === 'consent'" class="flex items-center justify-between gap-4 border-b border-[#F0EDE9] py-3">
        <span><b class="block">{{ $t('مديري بيشوف مستواي') }}</b><span class="text-sm text-subtle">{{ $t('المستوى والجاهزية بس، بدون إجاباتك.') }}</span></span>
        <ToggleSwitch :model-value="props.consents.manager_visibility" :label="$t('مديري بيشوف مستواي')" @update:model-value="(v) => set('manager_visibility', v)" />
      </div>
      <div class="flex items-center justify-between gap-4 border-b border-[#F0EDE9] py-3">
        <span><b class="block">{{ $t('ساعد بتحسين الوكلاء') }}</b><span class="text-sm text-subtle">{{ $t('تقييماتك وتعديلاتك بتوصل لفريق BAREE بدون اسمك ولا اسم مؤسستك.') }}</span></span>
        <ToggleSwitch :model-value="props.consents.improve_agents" :label="$t('ساعد بتحسين الوكلاء')" @update:model-value="(v) => set('improve_agents', v)" />
      </div>
      <div v-if="leaderboards" class="flex items-center justify-between gap-4 py-3">
        <span><b class="block">{{ $t('اسمي يظهر بلوحة المتصدّرين') }}</b><span class="text-sm text-subtle">{{ $t('بدونه بتظهر كـ "زميل".') }}</span></span>
        <ToggleSwitch :model-value="props.consents.leaderboard" :label="$t('اسمي يظهر بلوحة المتصدّرين')" @update:model-value="(v) => set('leaderboard', v)" />
      </div>
    </section>
    <section class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-stone bg-white p-6">
      <span><b class="block">{{ $t('نزّل بياناتك') }}</b><span class="text-sm text-subtle">{{ $t('ملف فيه ملفك وجوازك وتقييماتك ونشاطك.') }}</span></span>
      <a href="/settings/privacy/export" class="min-h-[44px] rounded-xl bg-ink px-5 py-2.5 font-bold text-white">{{ $t('تنزيل') }}</a>
    </section>
    <section class="rounded-3xl border border-[#F3B3B3] bg-white p-6">
      <b class="block">{{ $t('احذف حسابي') }}</b>
      <p class="text-sm text-subtle">{{ $t('بنحذف بياناتك الشخصية. اللي عملته للمؤسسة بيضل عندها بدون اسمك.') }}</p>
      <button v-if="!confirmDelete" type="button" class="mt-3 min-h-[44px] rounded-xl border border-[#F3B3B3] px-4 font-bold text-[#9B1C1C]" @click="confirmDelete = true">{{ $t('احذف الحساب') }}</button>
      <form v-else class="mt-3 flex flex-wrap gap-2" @submit.prevent="del.delete('/settings/account')">
        <label for="pw" class="sr-only">{{ $t('كلمة المرور') }}</label>
        <input id="pw" v-model="del.password" type="password" required autocomplete="current-password" :placeholder="$t('كلمة المرور للتأكيد')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
        <button type="submit" class="min-h-[44px] rounded-xl bg-[#9B1C1C] px-4 font-bold text-white">{{ $t('تأكيد الحذف') }}</button>
      </form>
    </section>
  </StudioShell>
</template>
