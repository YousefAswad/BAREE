import { currentLocale } from '@/i18n';
// Small JSON helper for live previews (segment counts, framework preview). Laravel's XSRF cookie protects it.
function xsrf(): string {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}

export async function postJson<T>(url: string, body: unknown): Promise<T> {
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
    body: JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error((data as { message?: string }).message ?? `HTTP ${response.status}`);
  return data as T;
}

/** Arabic-Indic digits in Arabic, unchanged in English. */
export const ar = (n: number | string | null | undefined) =>
  n === null || n === undefined ? '—' : currentLocale() === 'en' ? String(n) : String(n).replace(/[0-9]/g, (d) => '٠١٢٣٤٥٦٧٨٩'[Number(d)]);


/** Picks the current language from a {"ar","en"} content field, falling back to Arabic. */
export const t = (v: Record<string, string | null> | string | null | undefined, locale?: string) =>
  !v ? '' : typeof v === 'string' ? v : v[locale ?? currentLocale()] || v.ar || Object.values(v).find(Boolean) || '';
