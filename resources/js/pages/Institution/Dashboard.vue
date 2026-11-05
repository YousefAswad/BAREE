<script setup lang="ts">
import AdminShell from '@/components/admin/AdminShell.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import Panel from '@/components/admin/Panel.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { ar, t } from '@/lib/http';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const props = defineProps<{
  people: { total: number; active30: number };
  levels: { suppressed: boolean; total?: number; levels: { rank: number; key: string; name?: Record<string, string> | null; color?: string | null; count: number }[] };
  departments: { department: string | null; people: number | null; readiness: number | null; suppressed: boolean }[];
  credits: number | null;
  campaigns: { id: number; name: string; status: string; ends_at: string | null }[];
}>();

// Colour-blind-safe sequence with enough contrast on white; level colours from the framework win when present.
const COLORS = ['#8A93B8', '#6B7BD6', '#44529E', '#B5472B', '#D9702E', '#1F7A4D'];
const colorOf = (l: { rank: number; color?: string | null }) => l.color || COLORS[(l.rank - 1) % COLORS.length];
const pct = (part: number, whole: number) => (whole > 0 ? Math.round((100 * part) / whole) : 0);

const activeRate = computed(() => pct(props.people.active30, props.people.total));
const visibleDepartments = computed(() => props.departments.filter((d) => !d.suppressed));
const hiddenDepartments = computed(() => props.departments.filter((d) => d.suppressed).length);
const STATUS: Record<string, string> = { active: $t('جارية'), scheduled: $t('مجدولة') };
const date = (v: string | null) => (v ? new Date(v).toLocaleDateString(document.documentElement.lang === 'en' ? 'en-GB' : 'ar') : null);
</script>

<template>
  <AdminShell portal="institution" active="dashboard" :title="$t('لوحة المؤسسة')" :subtitle="$t('أرقام مجمّعة. أي مجموعة أقل من ٥ أشخاص مخفية لحماية الأفراد.')">
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard :label="$t('الموظفين')" :value="ar(people.total)" :hint="$t('مسجّلون في المنصة')" />
      <StatCard :label="$t('نشطين آخر ٣٠ يوم')" :value="ar(people.active30)" :hint="people.total ? $t('{p0}٪ من الموظفين', { p0: ar(activeRate) }) : undefined" />
      <StatCard :label="$t('حملات جارية أو مجدولة')" :value="ar(campaigns.length)" :hint="$t('حملات التقييم')" />
      <StatCard v-if="credits !== null" :label="$t('رصيد AI')" :value="ar(credits)" :hint="$t('أرصدة متبقية للمؤسسة')" dark />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Panel :title="$t('توزيع المستويات')" :subtitle="levels.suppressed ? undefined : $t('{p0} موظف أكملوا التقييم', { p0: ar(levels.total ?? 0) })">
        <EmptyState v-if="levels.suppressed" :title="$t('لا توجد بيانات كافية بعد')" :hint="$t('البيانات قليلة لسا، رح تظهر لما يكمّل ٥ موظفين التقييم على الأقل.')" />
        <template v-else>
          <div class="flex h-6 overflow-hidden rounded-full bg-stone" role="img" :aria-label="levels.levels.map((l) => `${t(l.name) || l.key}: ${l.count}`).join($t('، '))">
            <span v-for="l in levels.levels" :key="l.rank" class="block h-full" :style="{ width: `${(100 * l.count) / (levels.total || 1)}%`, background: colorOf(l) }" />
          </div>
          <ul class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <li v-for="l in levels.levels" :key="l.rank" class="flex items-center justify-between gap-3 rounded-xl bg-sand px-3 py-2">
              <span class="flex items-center gap-2 font-bold"><span class="h-3 w-3 rounded" :style="{ background: colorOf(l) }" />{{ t(l.name) || l.key }}</span>
              <span class="tabular-nums text-subtle"><b class="text-ink">{{ ar(l.count) }}</b> · {{ $t('{p0}٪', { p0: ar(pct(l.count, levels.total ?? 0)) }) }}</span>
            </li>
          </ul>
        </template>
      </Panel>

      <Panel :title="$t('الجاهزية حسب القسم')" :subtitle="$t('متوسط الجاهزية للأقسام التي اكتمل فيها حدّ الخصوصية')">
        <EmptyState v-if="!departments.length" :title="$t('لا توجد نتائج تقييم بعد')" :hint="$t('ستظهر الأقسام هنا بعد أن يكمل الموظفون أول تقييم.')" />
        <template v-else>
          <ul v-if="visibleDepartments.length" class="flex flex-col gap-4">
            <li v-for="d in visibleDepartments" :key="d.department ?? 'none'" class="flex flex-col gap-1.5">
              <div class="flex items-baseline justify-between gap-3 text-sm"><span class="font-bold text-ink">{{ d.department ?? $t('بدون قسم') }}<span class="ms-2 text-xs font-normal text-subtle">{{ $t('{p0} موظف', { p0: ar(d.people) }) }}</span></span><span class="font-display font-extrabold tabular-nums text-ink">{{ $t('{p0}٪', { p0: ar(d.readiness) }) }}</span></div>
              <div class="h-3 overflow-hidden rounded-full bg-stone" role="progressbar" :aria-valuenow="d.readiness ?? 0" aria-valuemin="0" aria-valuemax="100" :aria-label="d.department ?? $t('بدون قسم')"><div class="h-full rounded-full bg-indigo" :style="{ width: `${d.readiness ?? 0}%` }" /></div>
            </li>
          </ul>
          <EmptyState v-else :title="$t('كل الأقسام أقل من حدّ الخصوصية')" :hint="$t('أي قسم فيه أقل من ٥ أشخاص لا تُعرض نتائجه حماية للأفراد.')" />
          <p v-if="hiddenDepartments && visibleDepartments.length" class="rounded-xl bg-sand px-3 py-2 text-sm text-subtle">{{ $t('{p0} قسم مخفي (أقل من ٥ أشخاص)', { p0: ar(hiddenDepartments) }) }}</p>
        </template>
      </Panel>
    </div>

    <Panel :title="$t('حملات التقييم الجارية')" class="mt-6">
      <template #actions><Link href="/admin/campaigns" class="text-sm font-bold text-indigo-deep underline-offset-4 hover:underline">{{ $t('كل الحملات') }}</Link></template>
      <EmptyState v-if="!campaigns.length" :title="$t('لا توجد حملات جارية حاليًا')" :hint="$t('أنشئ حملة تقييم لتبدأ بقياس جاهزية فريقك.')">
        <Link href="/admin/campaigns" class="mt-2 rounded-xl bg-indigo px-4 py-2 text-sm font-bold text-white">{{ $t('إدارة الحملات') }}</Link>
      </EmptyState>
      <ul v-else class="flex flex-col gap-2">
        <li v-for="c in campaigns" :key="c.id">
          <Link :href="`/admin/campaigns/${c.id}`" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-stone bg-sand px-4 py-3 text-sm text-ink no-underline hover:border-indigo">
            <span class="font-bold">{{ c.name }}</span>
            <span class="flex items-center gap-3 text-subtle"><span v-if="c.ends_at">{{ $t('ينتهي {p0}', { p0: date(c.ends_at) }) }}</span><span class="rounded-full bg-mist px-3 py-1 text-xs font-bold text-indigo-deep">{{ STATUS[c.status] ?? c.status }}</span></span>
          </Link>
        </li>
      </ul>
    </Panel>
  </AdminShell>
</template>
