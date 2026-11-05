<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();
defineProps<{ columns: { key: string; label: string }[]; rows: Record<string, unknown>[]; empty?: string; rowKey?: string }>();
</script>

<template>
  <div class="overflow-x-auto rounded-2xl border border-stone bg-white">
    <table class="w-full min-w-[640px] border-collapse text-sm">
      <thead>
        <tr>
          <th v-for="c in columns" :key="c.key" scope="col" class="whitespace-nowrap border-b border-stone px-4 py-3 text-right text-xs font-extrabold text-subtle">{{ c.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, i) in rows" :key="String(row[rowKey ?? 'id'] ?? i)" class="border-b border-[#F0EDE9] last:border-0">
          <td v-for="c in columns" :key="c.key" class="px-4 py-3 align-middle">
            <slot :name="`cell-${c.key}`" :row="row">{{ row[c.key] ?? '—' }}</slot>
          </td>
        </tr>
        <tr v-if="!rows.length"><td :colspan="columns.length" class="px-4 py-10 text-center text-subtle">{{ empty ?? $t('ما في بيانات') }}</td></tr>
      </tbody>
    </table>
  </div>
</template>
