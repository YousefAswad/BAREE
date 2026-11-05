<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import ToggleSwitch from '@/components/admin/ToggleSwitch.vue';
import { router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ providers: { key: string; driver: string; base_url: string | null; enabled: boolean; has_platform_key: boolean; byok_tenants: number }[] }>();
const keys = reactive<Record<string, string>>({});

function toggle(p: { key: string; base_url: string | null }, enabled: boolean) {
  router.patch(`/platform/providers/${p.key}`, { enabled, base_url: p.base_url }, { preserveScroll: true });
}
function saveKey(key: string) {
  useForm({ secret: keys[key] }).put(`/platform/providers/${key}/key`, { preserveScroll: true, onSuccess: () => (keys[key] = '') });
}
</script>

<template>
  <AdminShell portal="platform" active="providers" :title="$t('مزوّدو AI')" :subtitle="$t('المفاتيح مشفّرة وما بترجع للمتصفح أبدًا. المؤسسات بتقدر تضيف مفتاحها الخاص.')">
    <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-4">
      <Panel v-for="p in props.providers" :key="p.key" :title="p.key">
        <template #actions><ToggleSwitch :model-value="p.enabled" :label="$t('تفعيل {p0}', { p0: p.key })" @update:model-value="(v) => toggle(p, v)" /></template>
        <p class="text-sm text-subtle">{{ $t('المحرّك: {p0} ·', { p0: p.driver }) }} <span dir="ltr">{{ p.base_url ?? '—' }}</span></p>
        <p class="text-sm">{{ $t('{p0}، {p1} مؤسسة بمفتاحها الخاص', { p0: p.has_platform_key ? $t('مفتاح المنصة مضبوط') : $t('ما في مفتاح منصة'), p1: p.byok_tenants }) }}</p>
        <form class="flex gap-2" @submit.prevent="saveKey(p.key)">
          <label :for="`k-${p.key}`" class="sr-only">{{ $t('مفتاح جديد') }}</label>
          <input :id="`k-${p.key}`" v-model="keys[p.key]" type="password" autocomplete="off" dir="ltr" :placeholder="$t('مفتاح جديد')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
          <button type="submit" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white">{{ $t('حفظ') }}</button>
        </form>
        <button type="button" class="min-h-[40px] self-start rounded-xl border border-stone px-3 text-sm font-bold" @click="router.post(`/platform/providers/${p.key}/test`, {}, { preserveScroll: true })">{{ $t('اختبار الاتصال') }}</button>
      </Panel>
    </div>
  </AdminShell>
</template>
