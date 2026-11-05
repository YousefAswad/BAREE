#!/usr/bin/env python3
"""
One-off codemod: wraps Arabic UI text in Vue SFCs with $t('...') so the page follows the user's language.
Arabic stays the source language (keys = Arabic text). Run, review the diff, then translate with i18n-check.mjs.
Usage: python3 scripts/i18n-wrap.py resources/js/pages/Foo.vue [...]
"""
import re, sys

AR = re.compile(r'[\u0600-\u06FF]')
SAFE = lambda s: not any(c in s for c in '{}|@\n\\') and not set(s) <= set('٠١٢٣٤٥٦٧٨٩')

def esc(s): return s.replace("'", "\\'")

def wrap_template_literals(text):
    # `نص ${x} نص` -> $t('نص {p0} نص', { p0: x })   (simple, non-nested interpolations)
    def repl(m):
        body = m.group(1)
        if not AR.search(re.sub(r'\$\{[^{}]*\}', '', body)):
            return m.group(0)
        args = []
        def sub(mm):
            args.append(mm.group(1).strip()); return '{p%d}' % (len(args) - 1)
        key = re.sub(r'\$\{([^{}]*)\}', sub, body)
        if any(c in key.replace('{p', '').replace('}', '') for c in '|@\\') or '\n' in key:
            return m.group(0)
        mapping = ', '.join(f'p{i}: {a}' for i, a in enumerate(args))
        return f"$t('{esc(key)}'" + (f", {{ {mapping} }})" if args else ")")
    return re.sub(r'`([^`]*)`', repl, text)

def wrap_expr_literals(text):
    # 'عربي' inside template expressions -> $t('عربي')  (skip already wrapped)
    return re.sub(r"(?<!\$t\()'([^'\n]*[\u0600-\u06FF][^'\n]*)'", lambda m: f"$t('{m.group(1)}')" if SAFE(m.group(1)) else m.group(0), text)

def convert_text_node(node):
    if not AR.search(re.sub(r'\{\{.*?\}\}', '', node, flags=re.S)):
        return node  # Arabic only inside mustaches (already handled) or none
    lead = re.match(r'^\s*', node).group(0); trail = re.search(r'\s*$', node).group(0)
    core = node.strip()
    parts = re.split(r'(\{\{.*?\}\})', core, flags=re.S)
    key, args = '', []
    for p in parts:
        if p.startswith('{{'):
            args.append(p[2:-2].strip()); key += '{p%d}' % (len(args) - 1)
        else:
            key += p
    key = re.sub(r'\s+', ' ', key).strip()
    if any(c in key.replace('{p', '').replace('}', '') for c in '|@\\'):
        return node
    if args:
        mapping = ', '.join(f'p{i}: {a}' for i, a in enumerate(args))
        return f"{lead}{{{{ $t('{esc(key)}', {{ {mapping} }}) }}}}{trail}"
    return f"{lead}{{{{ $t('{esc(key)}') }}}}{trail}"

def convert(source, force=False):
    head, sep, template = source.partition('<template>')
    if not sep or ('useI18n' in source and not force):
        return source
    already = 'useI18n' in source
    # template
    template = re.sub(r'(\{\{.*?\}\})', lambda m: wrap_template_literals(wrap_expr_literals(m.group(1))), template, flags=re.S)
    template = re.sub(r'(\s[:@][\w.:-]+=")([^"]*)(")', lambda m: m.group(1) + wrap_template_literals(wrap_expr_literals(m.group(2))) + m.group(3), template)
    template = re.sub(r'(\sv-[\w:-]+=")([^"]*)(")', lambda m: m.group(1) + wrap_expr_literals(m.group(2)) + m.group(3), template)
    template = re.sub(r'\s(placeholder|aria-label|title|alt|subtitle|label|description)="([^"]*[\u0600-\u06FF][^"]*)"', lambda m: f' :{m.group(1)}="$t(\'{esc(m.group(2))}\')"', template)
    template = re.sub(r'>([^<>]*)<', lambda m: '>' + convert_text_node(m.group(1)) + '<', template)
    uses_dir = 'dir="rtl"' in template
    template = template.replace('dir="rtl"', ':dir="dir"')
    # script: translate Arabic literals at setup time (pages remount on language switch)
    head = re.sub(r"(?<!\$t\()'([^'\n]*[\u0600-\u06FF][^'\n]*)'", lambda m: f"$t('{m.group(1)}')" if SAFE(m.group(1)) else m.group(0), head)
    if already:
        if uses_dir and 'useLocale()' not in head:
            head = head.replace("const { t: $t } = useI18n();", "const { t: $t } = useI18n();\nconst { dir } = useLocale();").replace("import { useI18n } from 'vue-i18n';", "import { useI18n } from 'vue-i18n';\nimport { useLocale } from '@/composables/useLocale';")
        return head + sep + template
    lines = head.split('\n')
    last_import = max(i for i, l in enumerate(lines) if l.startswith('import ')) if any(l.startswith('import ') for l in lines) else lines.index(next(l for l in lines if '<script' in l))
    inject = ["import { useI18n } from 'vue-i18n';"] + (["import { useLocale } from '@/composables/useLocale';"] if uses_dir else [])
    setup = ['', 'const { t: $t } = useI18n();'] + (['const { dir } = useLocale();'] if uses_dir else [])
    lines = lines[:last_import + 1] + inject + setup + lines[last_import + 1:]
    return '\n'.join(lines) + sep + template

if __name__ == '__main__':
    force = '--force' in sys.argv
    for path in [a for a in sys.argv[1:] if a != '--force']:
        src = open(path, encoding='utf-8').read()
        out = convert(src, force)
        if out != src:
            open(path, 'w', encoding='utf-8').write(out)
            print('converted', path)
