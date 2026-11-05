<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { t } from '@/lib/http';
import { router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  roles: { id: number; key: string; name: Record<string, string>; level: string; template: boolean; permissions: string[] }[];
  permissions: { key: string; group: string; scope: string; label: Record<string, string> }[];
  departments: { id: number; name: string }[];
}>();

const selection = reactive(Object.fromEntries(props.roles.map((r) => [r.id, [...r.permissions]])));
const cloneForm = useForm({ key: '', name: '' });
const assign = useForm({ user: '', department_id: null as number | null });
</script>

<template>
  <AdminShell portal="institution" active="roles" :title="$t('الأدوار والصلاحيات')" :subtitle="$t('القوالب من BAREE للقراءة. انسخ أي قالب وعدّله لمؤسستك.')">
    <div class="overflow-x-auto rounded-2xl border border-stone bg-white">
      <table class="w-full min-w-[820px] text-sm">
        <thead><tr><th scope="col" class="px-4 py-3 text-right">{{ $t('الصلاحية') }}</th><th v-for="r in roles" :key="r.id" scope="col" class="px-3 py-3 text-center text-xs">{{ t(r.name) }}<span v-if="r.template" class="block font-normal text-subtle">{{ $t('قالب') }}</span></th></tr></thead>
        <tbody>
          <tr v-for="p in permissions" :key="p.key" class="border-t border-[#F0EDE9]">
            <th scope="row" class="px-4 py-2 text-right font-bold">{{ t(p.label) }}<span class="block text-xs font-normal text-subtle">{{ p.scope }}</span></th>
            <td v-for="r in roles" :key="r.id" class="text-center">
              <input v-model="selection[r.id]" type="checkbox" :value="p.key" :disabled="r.template" :aria-label="$t('{p0} لدور {p1}', { p0: t(p.label), p1: t(r.name) })" class="h-5 w-5 accent-indigo" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
      <button v-for="r in roles.filter((r) => !r.template)" :key="r.id" type="button" class="min-h-[44px] rounded-xl bg-ink px-4 text-sm font-bold text-white" @click="router.put(`/admin/roles/${r.id}/permissions`, { permissions: selection[r.id] }, { preserveScroll: true })">{{ $t('احفظ «{p0}»', { p0: t(r.name) }) }}</button>
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('انسخ قالب')">
        <div v-for="r in roles.filter((r) => r.template)" :key="r.id" class="flex flex-wrap items-center gap-2 border-b border-[#F0EDE9] py-2">
          <span class="flex-1 text-sm font-bold">{{ t(r.name) }}</span>
          <button type="button" class="min-h-[36px] rounded-lg border border-stone px-3 text-xs font-bold" @click="cloneForm.key = `${r.key}_custom`; cloneForm.name = $t('{p0} (مخصّص)', { p0: t(r.name) }); cloneForm.post(`/admin/roles/${r.id}/clone`, { preserveScroll: true })">{{ $t('انسخ') }}</button>
        </div>
      </Panel>
      <Panel :title="$t('عيّن دور')">
        <p class="text-sm text-subtle">{{ $t('من صفحة المستخدم، أو أدخل المعرّف هنا. أدوار الأقسام بتحتاج قسم.') }}</p>
        <div class="grid gap-2">
          <input v-model="assign.user" dir="ltr" :placeholder="$t('معرّف المستخدم (ULID)')" :aria-label="$t('معرّف المستخدم')" class="min-h-[44px] rounded-xl border border-stone px-3" />
          <select v-model="assign.department_id" :aria-label="$t('القسم')" class="min-h-[44px] rounded-xl border border-stone px-3"><option :value="null">{{ $t('كل المؤسسة') }}</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
          <div class="flex flex-wrap gap-2"><button v-for="r in roles" :key="r.id" type="button" class="min-h-[40px] rounded-lg border border-stone px-3 text-xs font-bold" @click="assign.post(`/admin/roles/${r.id}/assignments`, { preserveScroll: true })">{{ t(r.name) }}</button></div>
        </div>
      </Panel>
    </div>
  </AdminShell>
</template>
