import { currentLocale, i18n } from '@/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useLocale() {
  const page = usePage();
  const locale = computed(() => ((page.props.locale as string) === 'en' ? 'en' : 'ar'));
  const dir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));

  function switchTo(next: 'ar' | 'en') {
    router.post('/locale', { locale: next }, {
      preserveScroll: true,
      onSuccess: () => {
        i18n.global.locale.value = next;
        document.documentElement.lang = next;
        document.documentElement.dir = next === 'ar' ? 'rtl' : 'ltr';
      },
    });
  }

  /** Locale-aware numbers: Arabic-Indic digits in Arabic, Western digits in English. */
  const n = (value: number | null | undefined, options: Intl.NumberFormatOptions = {}) =>
    value === null || value === undefined ? '—' : new Intl.NumberFormat(currentLocale() === 'ar' ? 'ar-EG' : 'en-US', options).format(value);
  const money = (cents: number, currency: string) => n(cents / 100, { style: 'currency', currency });
  const date = (value: string | null) => (value ? new Date(value).toLocaleDateString(currentLocale() === 'ar' ? 'ar' : 'en-GB') : '—');

  return { locale, dir, switchTo, n, money, date };
}
