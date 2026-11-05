<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar, t } from '@/lib/http';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  tenants: { data: Record<string, any>[]; links: { url: string | null; label: string; active: boolean }[] };
  plans: { key: string; name: Record<string, string> }[];
  filters: { q?: string; status?: string };
}>();

const q = ref(props.filters.q ?? '');
const showCreate = ref(false);
const form = useForm({ name: '', type: 'school', country: 'PS', plan_key: props.plans[0]?.key ?? '', owner_email: '', owner_name: '', trial_credits: 5000 });
const STATUS: Record<string, [string, string]> = { trial: [$t('تجريبية'), 'bg-mist text-indigo-deep'], active: [$t('نشطة'), 'bg-[#ECEFFC] text-[#3A4A8C]'], suspended: [$t('معلّقة'), 'bg-[#FDF0E4] text-[#9A4A12]'], cancelled: [$t('ملغاة'), 'bg-stone text-subtle'] };

function search() {
  router.get('/platform/tenants', { q: q.value, status: props.filters.status }, { preserveState: true, replace: true });
}
</script>

<template>
  <AdminShell portal="platform" active="tenants" :title="$t('المؤسسات')" :subtitle="$t('كل مؤسسة ببيئة معزولة: مستخدمينها وبياناتها ورصيدها.')">
    <template #actions>
      <button type="button" class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" @click="showCreate = !showCreate">{{ $t('مؤسسة جديدة') }}</button>
    </template>

    <Panel v-if="showCreate" :title="$t('تفعيل مؤسسة جديدة')" class="mb-6">
      <form class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4" @submit.prevent="form.post('/platform/tenants')">
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('اسم المؤسسة') }}<input v-model="form.name" required class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('النوع') }}
          <select v-model="form.type" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal">
            <option value="school">{{ $t('مدرسة') }}</option><option value="network">{{ $t('شبكة مدارس') }}</option><option value="university">{{ $t('جامعة') }}</option>
            <option value="training_center">{{ $t('مركز تدريب') }}</option><option value="ngo">{{ $t('مؤسسة أهلية') }}</option><option value="government">{{ $t('جهة حكومية') }}</option>
          </select></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الباقة') }}
          <select v-model="form.plan_key" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal"><option v-for="p in plans" :key="p.key" :value="p.key">{{ t(p.name) }}</option></select></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('بريد المالك') }}<input v-model="form.owner_email" type="email" dir="ltr" required class="min-h-[44px] rounded-xl border border-stone px-3 text-right font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('اسم المالك') }}<input v-model="form.owner_name" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('رصيد تجريبي') }}<input v-model.number="form.trial_credits" type="number" min="0" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
        <div class="flex items-end"><button type="submit" :disabled="form.processing" class="min-h-[44px] w-full rounded-xl bg-apricot font-bold text-ink">{{ $t('فعّل وأرسل الدعوة') }}</button></div>
      </form>
    </Panel>

    <form class="mb-4 flex gap-2" role="search" @submit.prevent="search">
      <label for="tq" class="sr-only">{{ $t('ابحث') }}</label>
      <input id="tq" v-model="q" type="search" :placeholder="$t('ابحث باسم المؤسسة')" class="min-h-[44px] w-full max-w-md rounded-xl border border-stone bg-white px-3" />
      <button type="submit" class="min-h-[44px] rounded-xl border border-stone bg-white px-4 font-bold">{{ $t('بحث') }}</button>
    </form>

    <DataTable
      :columns="[{ key: 'name', label: $t('المؤسسة') }, { key: 'type', label: $t('النوع') }, { key: 'users_count', label: $t('المستخدمين') }, { key: 'status', label: $t('الحالة') }, { key: 'trial_ends_at', label: $t('نهاية التجربة') }]"
      :rows="tenants.data"
      row-key="public_id"
    >
      <template #cell-name="{ row }"><Link :href="`/platform/tenants/${row.public_id}`" class="font-extrabold text-ink">{{ row.name }}</Link></template>
      <template #cell-users_count="{ row }">{{ ar(row.users_count as number) }}</template>
      <template #cell-status="{ row }"><span class="rounded-full px-2.5 py-1 text-xs font-extrabold" :class="STATUS[row.status as string]?.[1]">{{ STATUS[row.status as string]?.[0] }}</span></template>
      <template #cell-trial_ends_at="{ row }">{{ row.trial_ends_at ? new Date(row.trial_ends_at as string).toLocaleDateString('ar') : '—' }}</template>
    </DataTable>
    <nav class="mt-4 flex flex-wrap gap-1" :aria-label="$t('الصفحات')">
      <Link v-for="l in tenants.links" :key="l.label" :href="l.url ?? '#'" class="min-h-[40px] min-w-[40px] rounded-lg px-3 py-2 text-sm" :class="l.active ? 'bg-ink text-white' : 'bg-white'" v-html="l.label" />
    </nav>
  </AdminShell>
</template>
