<script setup lang="ts">
import { t } from '@/lib/http';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

type Field = { key: string; type: 'text' | 'number' | 'select' | 'multiselect'; required?: boolean; label: Record<string, string>; max?: number; options?: { value: string; label: Record<string, string> }[] };
defineProps<{ fields: Field[]; errors?: Record<string, string> }>();
const model = defineModel<Record<string, unknown>>({ required: true });
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <template v-for="f in fields" :key="f.key">
      <label v-if="f.type === 'select'" class="flex flex-col gap-1 text-sm font-bold">
        {{ t(f.label) }}<span v-if="!f.required" class="text-xs font-normal text-subtle">{{ $t('(اختياري)') }}</span>
        <select v-model="model[f.key]" :required="f.required" class="min-h-[48px] rounded-xl border border-stone bg-white px-3 font-normal">
          <option value="" disabled>{{ $t('اختر') }}</option>
          <option v-for="o in f.options" :key="o.value" :value="o.value">{{ t(o.label) }}</option>
        </select>
      </label>
      <label v-else-if="f.type === 'number'" class="flex flex-col gap-1 text-sm font-bold">
        {{ t(f.label) }}
        <input v-model.number="model[f.key]" type="number" min="0" :required="f.required" class="min-h-[48px] rounded-xl border border-stone px-3 font-normal" />
      </label>
      <label v-else class="flex flex-col gap-1 text-sm font-bold" :class="(f.max ?? 2000) > 300 ? 'sm:col-span-2' : ''">
        {{ t(f.label) }}<span v-if="!f.required" class="text-xs font-normal text-subtle">{{ $t('(اختياري)') }}</span>
        <textarea v-if="(f.max ?? 2000) > 300" v-model="model[f.key] as string" :required="f.required" :maxlength="f.max" rows="4" class="rounded-xl border border-stone p-3 font-normal leading-7" />
        <input v-else v-model="model[f.key] as string" :required="f.required" :maxlength="f.max" class="min-h-[48px] rounded-xl border border-stone px-3 font-normal" />
      </label>
      <p v-if="errors?.[f.key]" role="alert" class="text-sm text-[#9B1C1C] sm:col-span-2">{{ errors[f.key] }}</p>
    </template>
  </div>
</template>
