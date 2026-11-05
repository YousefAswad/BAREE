<script setup lang="ts">
import FileDrop from '@/components/studio/FileDrop.vue';
import StudioShell from '@/components/studio/StudioShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  workspace: { id: number; title: string; kind: string; instructions: string | null; memory: string[] | null; parent_id: number | null };
  role: 'owner' | 'editor' | 'viewer';
  children: { id: number; title: string; kind: string }[];
  outputs: { id: number; type: string; title: string; version: number; updated_at: string }[];
  files: { id: number; original_name: string; status: string; usage_mode: string }[];
  members: { id: string; name: string; role: string }[];
}>();

const canEdit = props.role !== 'viewer';
const settings = useForm({ instructions: props.workspace.instructions ?? '' });
const memory = ref<string[]>([...(props.workspace.memory ?? [])]);
const newFact = ref('');
const child = useForm({ kind: props.workspace.kind === 'course' ? 'unit' : 'lesson', title: '', parent_id: props.workspace.id });
const share = useForm({ user: '', role: 'viewer' });
const scenario = useForm({ workspace_id: props.workspace.id, title: '', topic: '' });
const uploads = ref<{ id: number; name: string; status: string }[]>([]);
const saveMemory = () => router.put(`/workspaces/${props.workspace.id}/memory`, { memory: memory.value }, { preserveScroll: true });
</script>

<template>
  <StudioShell :title="workspace.title" active="workspaces">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div><p class="text-sm font-bold text-indigo">{{ workspace.kind }}</p><h1 class="font-display text-3xl font-extrabold">{{ workspace.title }}</h1></div>
      <Link href="/studio" class="min-h-[44px] rounded-xl bg-apricot px-5 py-2.5 font-bold text-ink">{{ $t('اصنع شي جديد') }}</Link>
    </div>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
      <div class="flex flex-col gap-6">
        <section v-if="children.length || (canEdit && workspace.kind !== 'lesson' && workspace.kind !== 'project')" class="rounded-3xl border border-stone bg-white p-6">
          <h2 class="font-display mb-3 text-lg font-bold">{{ workspace.kind === 'course' ? $t('الوحدات') : $t('الدروس') }}</h2>
          <div class="flex flex-wrap gap-2"><Link v-for="c in children" :key="c.id" :href="`/workspaces/${c.id}`" class="rounded-xl bg-sand px-4 py-2 text-sm font-bold text-ink">{{ c.title }}</Link></div>
          <form v-if="canEdit && ['course', 'unit'].includes(workspace.kind)" class="mt-3 flex gap-2" @submit.prevent="child.post('/workspaces')">
            <input v-model="child.title" required :placeholder="$t('عنوان جديد')" :aria-label="$t('العنوان')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
            <button class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" type="submit">{{ $t('أضف') }}</button>
          </form>
        </section>

        <section class="rounded-3xl border border-stone bg-white p-6">
          <h2 class="font-display mb-3 text-lg font-bold">{{ $t('المحتوى') }}</h2>
          <ul class="flex flex-col gap-2">
            <li v-for="o in outputs" :key="o.id"><Link :href="`/outputs/${o.id}`" class="flex justify-between rounded-xl bg-sand px-4 py-3 text-sm text-ink"><span class="font-bold">{{ o.title }}</span><span class="text-subtle">{{ $t('إصدار {p0}', { p0: o.version }) }}</span></Link></li>
            <li v-if="!outputs.length" class="text-sm text-subtle">{{ $t('لسا ما في محتوى هون.') }}</li>
          </ul>
          <form v-if="canEdit" class="mt-4 flex flex-wrap gap-2 border-t border-[#F0EDE9] pt-4" @submit.prevent="scenario.post('/scenarios')">
            <input v-model="scenario.title" required :placeholder="$t('موقف تعليمي جديد: العنوان')" :aria-label="$t('عنوان الموقف')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
            <input v-model="scenario.topic" required :placeholder="$t('الموضوع')" :aria-label="$t('الموضوع')" class="min-h-[44px] flex-1 rounded-xl border border-stone px-3" />
            <button class="min-h-[44px] rounded-xl bg-ink px-4 font-bold text-white" type="submit">{{ $t('أنشئ موقف') }}</button>
          </form>
        </section>
      </div>

      <aside class="flex flex-col gap-6">
        <section class="rounded-3xl bg-ink p-6 text-white">
          <h2 class="font-display mb-2 text-lg font-bold">{{ $t('تعليمات المشروع') }}</h2>
          <p class="mb-2 text-xs text-[#B8BBD4]">{{ $t('كل وكيل بيشتغل هون بيلتزم فيها.') }}</p>
          <textarea v-model="settings.instructions" :readonly="!canEdit" rows="5" class="w-full rounded-xl border border-white/20 bg-transparent p-3 text-sm leading-7" :aria-label="$t('تعليمات المشروع')" />
          <button v-if="canEdit" type="button" class="mt-2 min-h-[40px] w-full rounded-xl bg-apricot font-bold text-ink" @click="settings.patch(`/workspaces/${workspace.id}`, { preserveScroll: true })">{{ $t('حفظ') }}</button>
        </section>
        <section class="rounded-3xl border border-stone bg-white p-6">
          <h2 class="font-display mb-2 text-lg font-bold">{{ $t('ذاكرة المشروع') }}</h2>
          <p class="mb-2 text-xs text-subtle">{{ $t('معلومات بيتذكّرها الوكيل. بتقدر تحذف أي وحدة.') }}</p>
          <ul class="flex flex-col gap-2 text-sm"><li v-for="(m, i) in memory" :key="i" class="flex items-center justify-between gap-2 rounded-xl bg-sand px-3 py-2">{{ m }}<button v-if="canEdit" type="button" :aria-label="$t('احذف: {p0}', { p0: m })" class="h-8 w-8 rounded-lg" @click="memory.splice(i, 1); saveMemory()">×</button></li></ul>
          <form v-if="canEdit" class="mt-2 flex gap-2" @submit.prevent="memory.push(newFact); newFact = ''; saveMemory()">
            <input v-model="newFact" required maxlength="300" :placeholder="$t('مثلًا: الصف فيه ٣ طلاب دمج')" :aria-label="$t('معلومة جديدة')" class="min-h-[40px] flex-1 rounded-xl border border-stone px-3 text-sm" />
            <button class="min-h-[40px] rounded-xl border border-stone px-3 text-sm font-bold" type="submit">{{ $t('أضف') }}</button>
          </form>
        </section>
        <section class="rounded-3xl border border-stone bg-white p-6">
          <h2 class="font-display mb-2 text-lg font-bold">{{ $t('ملفات المشروع') }}</h2>
          <ul class="mb-3 flex flex-col gap-1 text-sm"><li v-for="f in files" :key="f.id" class="flex justify-between"><span class="truncate">{{ f.original_name }}</span><span class="text-xs text-subtle">{{ f.status }}</span></li></ul>
          <FileDrop v-if="canEdit" v-model="uploads" :workspace-id="workspace.id" />
        </section>
        <section v-if="role === 'owner'" class="rounded-3xl border border-stone bg-white p-6">
          <h2 class="font-display mb-2 text-lg font-bold">{{ $t('المشاركة') }}</h2>
          <ul class="mb-2 text-sm"><li v-for="m in members" :key="m.id">{{ m.name }} · {{ m.role === 'editor' ? $t('محرّر') : $t('مشاهد') }}</li></ul>
          <form class="flex flex-col gap-2" @submit.prevent="share.post(`/workspaces/${workspace.id}/members`, { preserveScroll: true })">
            <input v-model="share.user" required dir="ltr" :placeholder="$t('معرّف الزميل')" :aria-label="$t('معرّف الزميل')" class="min-h-[40px] rounded-xl border border-stone px-3 text-sm" />
            <select v-model="share.role" :aria-label="$t('الدور')" class="min-h-[40px] rounded-xl border border-stone px-3 text-sm"><option value="viewer">{{ $t('مشاهد') }}</option><option value="editor">{{ $t('محرّر') }}</option></select>
            <button class="min-h-[40px] rounded-xl bg-ink text-sm font-bold text-white" type="submit">{{ $t('شارك') }}</button>
          </form>
        </section>
      </aside>
    </div>
  </StudioShell>
</template>
