<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { ar } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  tenant: { public_id: string; name: string; type: string; status: string; users_count: number; trial_ends_at: string | null; settings: Record<string, any> };
  credits: number;
  supportSessions: { id: number; reason: string; started_at: string; expires_at: string; ended_at: string | null }[];
}>();

const status = useForm({ status: props.tenant.status, reason: '' });
const credit = useForm({ amount: 10000, reason: 'top_up' });
const support = useForm({ reason: '' });
</script>

<template>
  <AdminShell portal="platform" active="tenants" :title="tenant.name" :subtitle="$t('حالة المؤسسة: {p0}', { p0: tenant.status })">
    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-4">
      <StatCard :label="$t('المستخدمين')" :value="ar(tenant.users_count)" />
      <StatCard :label="$t('رصيد AI')" :value="ar(credits)" dark />
      <StatCard :label="$t('وضع الخصوصية')" :value="tenant.settings?.privacy?.mode ?? '—'" />
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
      <Panel :title="$t('الحالة')">
        <form class="flex flex-col gap-3" @submit.prevent="status.patch(`/platform/tenants/${tenant.public_id}/status`)">
          <select v-model="status.status" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="trial">{{ $t('تجريبية') }}</option><option value="active">{{ $t('نشطة') }}</option><option value="suspended">{{ $t('معلّقة') }}</option><option value="cancelled">{{ $t('ملغاة') }}</option></select>
          <label class="text-sm font-bold">{{ $t('السبب (بيتسجّل بسجل التدقيق)') }}<textarea v-model="status.reason" required minlength="5" class="mt-1 w-full rounded-xl border border-stone p-3 font-normal" /></label>
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink font-bold text-white">{{ $t('حفظ') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('منح رصيد')">
        <form class="flex flex-col gap-3" @submit.prevent="credit.post(`/platform/tenants/${tenant.public_id}/credits`)">
          <input v-model.number="credit.amount" type="number" min="1" class="min-h-[44px] rounded-xl border border-stone px-3" :aria-label="$t('الكمية')" />
          <select v-model="credit.reason" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="top_up">{{ $t('شحن') }}</option><option value="adjustment">{{ $t('تسوية') }}</option><option value="refund">{{ $t('استرداد') }}</option></select>
          <button type="submit" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink">{{ $t('امنح') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('جلسة دعم (قراءة فقط، ٣٠ دقيقة)')" dark>
        <form class="flex flex-col gap-3" @submit.prevent="support.post(`/platform/tenants/${tenant.public_id}/support`)">
          <label class="text-sm">{{ $t('السبب، وبيوصل لمالك المؤسسة') }}<textarea v-model="support.reason" required minlength="10" class="mt-1 w-full rounded-xl border border-white/20 bg-transparent p-3" /></label>
          <button type="submit" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink">{{ $t('ابدأ الجلسة') }}</button>
        </form>
        <ul class="flex flex-col gap-2 text-sm">
          <li v-for="s in supportSessions" :key="s.id" class="rounded-xl bg-white/10 p-3">{{ s.reason }}<span class="block text-xs text-[#B8BBD4]">{{ new Date(s.started_at).toLocaleString('ar') }}</span></li>
        </ul>
      </Panel>
    </div>
  </AdminShell>
</template>
