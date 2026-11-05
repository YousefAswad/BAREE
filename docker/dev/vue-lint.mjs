// Informational pre-build lint: compiles every SFC template with the project's own Vue compiler and lists ALL syntax errors at once
// (vite stops at the first one and a full build takes minutes). Never fails the setup; the real build remains the gate.
// Run from the app root:  node /overlay/docker/dev/vue-lint.mjs
import { createRequire } from 'node:module';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const require = createRequire(join(process.cwd(), 'package.json'));
const sfc = require('vue/compiler-sfc');
const walk = (dir) => readdirSync(dir).flatMap((f) => (statSync(join(dir, f)).isDirectory() ? walk(join(dir, f)) : f.endsWith('.vue') ? [join(dir, f)] : []));
const msg = (e) => (typeof e === 'string' ? e : e.message);
let bad = 0, files = 0;

for (const file of walk('resources/js')) {
  files++;
  const source = readFileSync(file, 'utf8');
  const { descriptor, errors } = sfc.parse(source, { filename: file });
  const report = (e, offset = 0) => { bad++; console.log(`${file}:${(e.loc?.start?.line ?? 1) + offset}  ${msg(e).split('\n')[0]}`); };
  errors.forEach((e) => report(e));
  if (!descriptor.template) continue;
  const ts = /lang=["']ts["']/.test(source);
  const out = sfc.compileTemplate({
    source: descriptor.template.content, filename: file, id: 'baree-lint',
    compilerOptions: ts ? { expressionPlugins: ['typescript'] } : {},
  });
  out.errors.forEach((e) => report(e, descriptor.template.loc.start.line - 1));
}
console.log(bad ? `vue-lint: ${bad} template problem(s) in ${files} files (the build below will fail on the first)` : `vue-lint: ${files} SFC templates compile cleanly`);
