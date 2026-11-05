<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import Panel from '@/components/admin/Panel.vue';
import { ar } from '@/lib/http';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

defineProps<{
  campuses: { id: number; name: string; city: string | null }[];
  departments: { id: number; name: string; kind: string; campus_id: number | null; users_count: number }[];
  jobTitles: { id: number; name: string; role_template_key: string }[];
  roleTemplates: string[];
}>();
const campus = useForm({ name: '', city: '' });
const dept = useForm({ name: '', kind: 'academic', campus_id: null as number | null });
const title = useForm({ name: '', role_template_key: 'teacher' });
</script>

<template>
  <AdminShell portal="institution" active="structure" :title="$t('الهيكل والأقسام')" :subtitle="$t('المسمّى الوظيفي مربوط بقالب دور من BAREE، وهو اللي بيحدد الأدوات والتدريب لكل موظف.')">
    <div class="grid gap-6 xl:grid-cols-3">
      <Panel :title="$t('الفروع')">
        <ul class="flex flex-col gap-2 text-sm"><li v-for="c in campuses" :key="c.id" class="rounded-xl bg-sand p-3 font-bold">{{ c.name }} <span class="font-normal text-subtle">{{ c.city }}</span></li></ul>
        <form class="flex gap-2" @submit.prevent="campus.post('/admin/structure/campuses', { preserveScroll: true, onSuccess: () => campus.reset() })">
          <input v-model="campus.name" required :placeholder="$t('فرع جديد')" :aria-label="$t('اسم الفرع')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
          <button class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" type="submit">{{ $t('أضف') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('الأقسام')">
        <ul class="flex flex-col gap-2 text-sm"><li v-for="d in departments" :key="d.id" class="flex justify-between rounded-xl bg-sand p-3"><span class="font-bold">{{ d.name }}</span><span class="text-subtle">{{ $t('{p0}، {p1}', { p0: d.kind === 'academic' ? $t('أكاديمي') : $t('إداري'), p1: ar(d.users_count) }) }}</span></li></ul>
        <form class="grid gap-2" @submit.prevent="dept.post('/admin/structure/departments', { preserveScroll: true, onSuccess: () => dept.reset('name') })">
          <input v-model="dept.name" required :placeholder="$t('قسم جديد')" :aria-label="$t('اسم القسم')" class="min-h-[44px] rounded-xl border border-stone px-3" />
          <select v-model="dept.kind" :aria-label="$t('نوع القسم')" class="min-h-[44px] rounded-xl border border-stone px-3"><option value="academic">{{ $t('أكاديمي') }}</option><option value="administrative">{{ $t('إداري') }}</option></select>
          <select v-model="dept.campus_id" :aria-label="$t('الفرع')" class="min-h-[44px] rounded-xl border border-stone px-3"><option :value="null">{{ $t('كل الفروع') }}</option><option v-for="c in campuses" :key="c.id" :value="c.id">{{ c.name }}</option></select>
          <button class="min-h-[44px] rounded-xl bg-ink font-bold text-white" type="submit">{{ $t('أضف') }}</button>
        </form>
      </Panel>
      <Panel :title="$t('المسمّيات الوظيفية')">
        <ul class="flex flex-col gap-2 text-sm"><li v-for="j in jobTitles" :key="j.id" class="flex justify-between rounded-xl bg-sand p-3"><span class="font-bold">{{ j.name }}</span><code dir="ltr" class="text-xs">{{ j.role_template_key }}</code></li></ul>
        <form class="grid gap-2" @submit.prevent="title.post('/admin/structure/job-titles', { preserveScroll: true, onSuccess: () => title.reset('name') })">
          <input v-model="title.name" required :placeholder="$t('مثلًا: معلّمة علوم')" :aria-label="$t('المسمّى')" class="min-h-[44px] rounded-xl border border-stone px-3" />
          <select v-model="title.role_template_key" :aria-label="$t('قالب الدور')" class="min-h-[44px] rounded-xl border border-stone px-3"><option v-for="r in roleTemplates" :key="r" :value="r">{{ r }}</option></select>
          <button class="min-h-[44px] rounded-xl bg-ink font-bold text-white" type="submit">{{ $t('أضف') }}</button>
        </form>
      </Panel>
    </div>
  </AdminShell>
</template>
