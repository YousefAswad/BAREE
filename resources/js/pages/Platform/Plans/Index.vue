<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ plans: any[] }>();
const state = reactive(Object.fromEntries(props.plans.map((p) => [p.id, {
  name_ar: p.name.ar, name_en: p.name.en, price_per_seat_cents: p.price_per_seat_cents, currency: p.currency, interval: p.interval, active: p.active,
  limits: { users: p.limits.users, monthly_credits: p.limits.monthly_credits, storage_gb: p.limits.storage_gb, custom_agents: !!p.limits.custom_agents, media: !!p.limits.media, api: !!p.limits.api },
}])));
const save = (id: number) => router.put(`/platform/plans/${id}`, state[id], { preserveScroll: true });
</script>

<template>
  <AdminShell portal="platform" active="plans" :title="$t('الباقات')" :subtitle="$t('التغييرات بتنطبق على الاشتراكات الجديدة وعند التجديد. فارغ = بدون حد.')">
    <div class="grid gap-4 lg:grid-cols-2">
      <Panel v-for="p in plans" :key="p.id" :title="p.key">
        <template #actions><ToggleSwitch v-model="state[p.id].active" :label="$t('تفعيل {p0}', { p0: p.key })" /></template>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <label class="flex flex-col gap-1 font-bold">{{ $t('الاسم بالعربي') }}<input v-model="state[p.id].name_ar" class="min-h-[40px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 font-bold">{{ $t('الاسم بالإنجليزي') }}<input v-model="state[p.id].name_en" dir="ltr" class="min-h-[40px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 font-bold">{{ $t('سعر المقعد (سنت)') }}<input v-model.number="state[p.id].price_per_seat_cents" type="number" min="0" class="min-h-[40px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 font-bold">{{ $t('العملة') }}<input v-model="state[p.id].currency" dir="ltr" maxlength="3" class="min-h-[40px] rounded-xl border border-stone px-3 font-normal uppercase" /></label>
          <label class="flex flex-col gap-1 font-bold">{{ $t('المستخدمين') }}<input v-model.number="state[p.id].limits.users" type="number" min="1" class="min-h-[40px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 font-bold">{{ $t('رصيد شهري') }}<input v-model.number="state[p.id].limits.monthly_credits" type="number" min="0" class="min-h-[40px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex items-center gap-2"><input v-model="state[p.id].limits.media" type="checkbox" />{{ $t('وسائط متقدمة') }}</label>
          <label class="flex items-center gap-2"><input v-model="state[p.id].limits.custom_agents" type="checkbox" />{{ $t('وكلاء مخصّصين') }}</label>
        </div>
        <button type="button" class="min-h-[44px] rounded-xl bg-ink font-bold text-white" @click="save(p.id)">{{ $t('حفظ') }}</button>
      </Panel>
    </div>
  </AdminShell>
</template>
