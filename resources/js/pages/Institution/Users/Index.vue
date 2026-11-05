<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import DataTable from '@/components/admin/DataTable.vue';
import Panel from '@/components/admin/Panel.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  users: { data: Record<string, any>[] };
  pendingInvitations: { id: number; email: string; role_key: string; expires_at: string }[];
  departments: { id: number; name: string }[];
  jobTitles: { id: number; name: string; role_template_key: string }[];
  filters: { q?: string; department?: string; status?: string };
}>();

const q = ref(props.filters.q ?? '');
const invite = useForm({ email: '', name: '', department_id: null as number | null, job_title_id: null as number | null, role_key: 'staff' });
const upload = useForm({ file: null as File | null });
</script>

<template>
  <AdminShell portal="institution" active="users" :title="$t('المستخدمين')" :subtitle="$t('المستوى الفردي بيظهر بس إذا سياسة المؤسسة وموافقة الموظف بتسمح.')">
    <div class="mb-6 grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('دعوة موظف')">
        <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="invite.post('/admin/invitations', { preserveScroll: true, onSuccess: () => invite.reset('email', 'name') })">
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('البريد') }}<input v-model="invite.email" type="email" dir="ltr" required class="min-h-[44px] rounded-xl border border-stone px-3 text-right font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('الاسم') }}<input v-model="invite.name" class="min-h-[44px] rounded-xl border border-stone px-3 font-normal" /></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('القسم') }}<select v-model="invite.department_id" class="min-h-[44px] rounded-xl border border-stone px-3"><option :value="null">—</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></label>
          <label class="flex flex-col gap-1 text-sm font-bold">{{ $t('المسمّى') }}<select v-model="invite.job_title_id" class="min-h-[44px] rounded-xl border border-stone px-3"><option :value="null">—</option><option v-for="j in jobTitles" :key="j.id" :value="j.id">{{ j.name }}</option></select></label>
          <button type="submit" :disabled="invite.processing" class="min-h-[44px] rounded-xl bg-ink font-bold text-white sm:col-span-2">{{ $t('أرسل الدعوة') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('رفع جماعي (CSV)')">
        <p class="text-sm leading-7 text-subtle">{{ $t('أعمدة: الاسم، البريد، القسم، المسمّى الوظيفي. بنفحص كل صف قبل ما نرسل أي دعوة.') }}</p>
        <form class="flex flex-col gap-3" @submit.prevent="upload.post('/admin/imports')">
          <label class="text-sm font-bold">{{ $t('الملف') }}<input type="file" accept=".csv,text/csv" required class="mt-1 block w-full text-sm" @change="upload.file = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label>
          <button type="submit" :disabled="upload.processing" class="min-h-[44px] rounded-xl bg-apricot font-bold text-ink">{{ $t('افحص الملف') }}</button>
        </form>
      </Panel>
    </div>

    <form class="mb-4 flex gap-2" role="search" @submit.prevent="router.get('/admin/users', { q }, { preserveState: true, replace: true })">
      <label for="uq" class="sr-only">{{ $t('ابحث') }}</label>
      <input id="uq" v-model="q" type="search" :placeholder="$t('ابحث بالاسم أو البريد')" class="min-h-[44px] w-full max-w-md rounded-xl border border-stone bg-white px-3" />
      <button type="submit" class="min-h-[44px] rounded-xl border border-stone bg-white px-4 font-bold">{{ $t('بحث') }}</button>
    </form>
    <DataTable :columns="[{ key: 'name', label: $t('الموظف') }, { key: 'department', label: $t('القسم') }, { key: 'job_title', label: $t('المسمّى') }, { key: 'assessed', label: $t('التقييم') }, { key: 'level', label: $t('المستوى') }, { key: 'status', label: $t('الحالة') }]" :rows="users.data">
      <template #cell-name="{ row }"><span class="font-bold">{{ row.name }}</span><span dir="ltr" class="block text-xs text-subtle">{{ row.email }}</span></template>
      <template #cell-assessed="{ row }">{{ row.assessed ? $t('مكتمل') : $t('لم يكمل') }}</template>
      <template #cell-level="{ row }"><span v-if="row.level" class="rounded-full bg-ink px-2.5 py-1 text-xs font-bold text-apricot">{{ row.level.level }}</span><span v-else class="text-xs text-subtle">{{ $t('مخفي') }}</span></template>
      <template #cell-status="{ row }">
        <button type="button" class="min-h-[36px] rounded-lg border border-stone px-2 text-xs" @click="router.patch(`/admin/users/${row.id}`, { status: row.status === 'active' ? 'disabled' : 'active' }, { preserveScroll: true })">{{ row.status === 'active' ? $t('تعطيل') : $t('تفعيل') }}</button>
      </template>
    </DataTable>

    <Panel v-if="pendingInvitations.length" :title="$t('دعوات بانتظار القبول')" class="mt-6">
      <div v-for="inv in pendingInvitations" :key="inv.id" class="flex items-center justify-between gap-3 border-b border-[#F0EDE9] py-2 text-sm">
        <span dir="ltr">{{ inv.email }}</span>
        <button type="button" class="min-h-[36px] rounded-lg border border-stone px-3 text-xs font-bold" @click="router.delete(`/admin/invitations/${inv.id}`, { preserveScroll: true })">{{ $t('إلغاء') }}</button>
      </div>
    </Panel>
  </AdminShell>
</template>
