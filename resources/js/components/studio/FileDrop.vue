<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{ workspaceId?: number | null }>();
const files = defineModel<{ id: number; name: string; status: string }[]>({ default: () => [] });
const error = ref('');
const busy = ref(false);

function xsrf() {
  const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

async function upload(list: FileList | null) {
  if (!list) return;
  error.value = '';
  busy.value = true;
  for (const file of Array.from(list).slice(0, 10 - files.value.length)) {
    const body = new FormData();
    body.append('file', file);
    if (props.workspaceId) body.append('workspace_id', String(props.workspaceId));
    const res = await fetch('/files', { method: 'POST', body, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { error.value = data.message ?? $t('تعذّر رفع الملف'); continue; }
    files.value.push(data);
    poll(data.id);
  }
  busy.value = false;
}

async function poll(id: number) {
  for (let i = 0; i < 30; i++) {
    await new Promise((r) => setTimeout(r, 2000));
    const res = await fetch(`/files/${id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const data = await res.json();
    const item = files.value.find((f) => f.id === id);
    if (item) item.status = data.status;
    if (['indexed', 'rejected'].includes(data.status)) return;
  }
}

const LABEL: Record<string, string> = { pending: $t('بيتفحص'), scanned: $t('بيتقرأ'), indexed: $t('جاهز'), rejected: $t('مرفوض') };
</script>

<template>
  <div class="flex flex-col gap-3">
    <label class="flex min-h-[96px] cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-lavender bg-sand p-4 text-center text-sm">
      <span class="font-bold">{{ $t('اسحب ملفات أو اضغط للاختيار') }}</span>
      <span class="text-xs text-subtle">{{ $t('PDF, Word, PowerPoint, Excel, CSV. كل ملف بيتفحص من الفيروسات قبل ما يقرأه الوكيل.') }}</span>
      <input type="file" multiple class="sr-only" accept=".pdf,.docx,.pptx,.xlsx,.csv,.txt,.md" :disabled="busy" @change="upload(($event.target as HTMLInputElement).files)" />
    </label>
    <ul class="flex flex-col gap-2">
      <li v-for="f in files" :key="f.id" class="flex items-center justify-between rounded-xl bg-white px-3 py-2 text-sm">
        <span class="truncate">{{ f.name }}</span>
        <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="f.status === 'indexed' ? 'bg-mist text-indigo-deep' : f.status === 'rejected' ? 'bg-[#FBE3E3] text-[#9B1C1C]' : 'bg-[#FDF0E4] text-[#9A4A12]'">{{ LABEL[f.status] ?? f.status }}</span>
      </li>
    </ul>
    <p v-if="error" role="alert" class="text-sm text-[#9B1C1C]">{{ error }}</p>
  </div>
</template>
