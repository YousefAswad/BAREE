#!/usr/bin/env node
// Lists Vue files that still contain Arabic text outside $t(...) and keys missing from en.json.
// Usage: node scripts/i18n-check.mjs   (exit code 1 if anything is missing)
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../resources/js/', import.meta.url).pathname;
const en = JSON.parse(readFileSync(join(root, 'i18n/en.json'), 'utf8'));
const arabic = /[\u0600-\u06FF]/;
const walk = (dir) => readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? walk(join(dir, f)) : f.endsWith('.vue') ? [join(dir, f)] : []));

let problems = 0;
for (const file of walk(root)) {
  const source = readFileSync(file, 'utf8');
  if (!source.includes("useI18n")) {
    if (arabic.test(source)) { console.log(`NOT CONVERTED  ${file.replace(root, '')}`); problems++; }
    continue;
  }
  const template = source.slice(source.indexOf('<template>')).replace(/\$t\('(?:[^'\\]|\\.)*'/g, '').replace(/<!--[\s\S]*?-->/g, '');
  if (arabic.test(template)) {
    const line = template.split('\n').find((l) => arabic.test(l));
    console.log(`UNWRAPPED      ${file.replace(root, '')}: ${line.trim().slice(0, 90)}`);
    problems++;
  }
  for (const [, key] of source.matchAll(/\$t\('([^']+)'/g)) {
    if (arabic.test(key) && !(key in en)) { console.log(`MISSING EN     ${file.replace(root, '')}: ${key}`); problems++; }
  }
}
console.log(problems ? `\n${problems} item(s) to translate.` : 'All converted files are translated.');
process.exit(problems ? 1 : 0);
