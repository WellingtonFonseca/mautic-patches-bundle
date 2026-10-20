// Runs the REAL script that EmailBuilderErrorModalSubscriber puts on the page (taken from the PHP class, not copied): the
// parser that turns core's one-sentence validation error of the email builder into intro, list items and outro, and the
// swap of the modal's body and footer on a fake GrapesJS dialog.
//
// Run inside the Mautic container, no extra dependencies (Node's built-in runner):
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD   = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';
const CLASS_FILE = path.resolve(__dirname, '../../EventListener/EmailBuilderErrorModalSubscriber.php');

const SCRIPT = execFileSync('php', ['-r',
    `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(CLASS_FILE)}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\EmailBuilderErrorModalSubscriber::script('Fechar');`,
], {encoding: 'utf8'});

function el(tag) {
    return {
        tag, className: '', children: [], text: '', parentNode: null,
        classList: {add(c) { this.owner.className += ` ${c}`; }},
        appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
        removeChild(c) { this.children.splice(this.children.indexOf(c), 1); },
        replaceChild(n, o) { this.children[this.children.indexOf(o)] = n; n.parentNode = this; },
        addEventListener() {},
        get firstChild() { return this.children[0]; },
        get textContent() { return this.text; },
    };
}

function load() {
    let observed;
    const window = {};
    const document = {
        documentElement: {},
        createElement: (tag) => { const e = el(tag); e.classList.owner = e; return e; },
        createTextNode: (text) => ({text, parentNode: null}),
        querySelectorAll: () => [],
    };
    const context = {window, document, MutationObserver: function (fn) { this.observe = () => { observed = fn; }; }};
    vm.runInNewContext(SCRIPT, context);

    // JSON round trip: the arrays come from another realm (vm), which deepStrictEqual does not take as equal.
    const parse = (text) => JSON.parse(JSON.stringify(window.mauticPatchesEmailBuilderErrorModal.parse(text)));

    return {parse, document, observed};
}

const SENTENCE = 'Há alguns erros de validação do formulário (fromName: Nome do Remetente é obrigatório. Acesse a aba Avançado., '
    + 'fromAddress: E-mail do Remetente é obrigatório. Acesse a aba Avançado., '
    + 'customHtml: Há variáveis sem o prefixo n8n_, acesse a aba Variáveis N8N.) Por favor, feche o construtor e arrume-os.';

test('one list item per message, without the field names; the intro and the outro stay', () => {
    const r = load().parse(SENTENCE);

    assert.strictEqual(r.intro, 'Há alguns erros de validação do formulário');
    assert.deepStrictEqual(r.items, [
        'Nome do Remetente é obrigatório. Acesse a aba Avançado.',
        'E-mail do Remetente é obrigatório. Acesse a aba Avançado.',
        'Há variáveis sem o prefixo n8n_, acesse a aba Variáveis N8N.',
    ]);
    assert.strictEqual(r.outro, 'Por favor, feche o construtor e arrume-os.');
});

test('a single error gives a single item', () => {
    const r = load().parse('Há alguns erros (customHtml: Mapeie as variáveis na aba Variáveis N8N.) Por favor, feche.');

    assert.deepStrictEqual(r.items, ['Mapeie as variáveis na aba Variáveis N8N.']);
});

test('a text that is not the sentence is left alone', () => {
    assert.strictEqual(load().parse('Algo deu errado'), null);
    assert.strictEqual(load().parse('Sem itens () aqui'), null);
});
