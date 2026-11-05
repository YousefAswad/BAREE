import { createI18n } from 'vue-i18n';
import en from './en.json';

/**
 * Arabic is the source language: message keys ARE the Arabic strings, so untranslated keys render as Arabic.
 * English lives in en.json (key = Arabic, value = English). Register in app.ts:
 *   createApp(...).use(plugin).use(createBareeI18n(props.initialPage.props.locale as string))
 */
/**
 * Identity catalogue for Arabic: vue-i18n returns a MISSING key verbatim and skips interpolation, so Arabic strings with {p0}/{n}
 * placeholders rendered raw braces. Registering key -> key makes them compile and interpolate like any other message.
 */
const arIdentity: Record<string, string> = Object.fromEntries(Object.keys(en).map((k) => [k, k]));

export const i18n = createI18n({
  legacy: false,
  locale: 'ar',
  fallbackLocale: 'ar',
  messages: { en, ar: arIdentity },
  missingWarn: false,
  fallbackWarn: false,
  warnHtmlMessage: false,
});

export function createBareeI18n(locale?: string) {
  i18n.global.locale.value = locale === 'en' ? 'en' : 'ar';
  if (typeof document !== 'undefined') {
    document.documentElement.lang = i18n.global.locale.value;
    document.documentElement.dir = i18n.global.locale.value === 'ar' ? 'rtl' : 'ltr';
  }
  return i18n;
}

export const currentLocale = () => i18n.global.locale.value as 'ar' | 'en';
