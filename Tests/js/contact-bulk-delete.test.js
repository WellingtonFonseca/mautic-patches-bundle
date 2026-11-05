// The plain functions of the bulk delete page (Resources/js/contact-bulk-delete.js): the texts, the percent, the typed word and the loop of
// batches. The glue with the page (the table, the alert, the button, the modal) is checked in the browser
// (scripts/<version>/e2e/tests/contact-bulk-delete.spec.js).
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/contact-bulk-delete.js');

test('fill: every %name% is replaced by its value, the other text stays', () => {
    assert.strictEqual(api.fill('%done% de %total% excluídos', {done: 2, total: 5}), '2 de 5 excluídos');
    assert.strictEqual(api.fill('sem variáveis', {done: 1}), 'sem variáveis');
});

test('foundText: none, one and many have their own text', () => {
    const texts = {none: 'Nenhum contato encontrado.', one: '1 contato encontrado.', many: '%count% contatos encontrados.'};
    assert.strictEqual(api.foundText(texts, 0), 'Nenhum contato encontrado.');
    assert.strictEqual(api.foundText(texts, 1), '1 contato encontrado.');
    assert.strictEqual(api.foundText(texts, 2), '2 contatos encontrados.');
    assert.strictEqual(api.foundText(texts, 1000), '1000 contatos encontrados.');
});

test('percent: rounds, never passes 100 and never divides by zero', () => {
    assert.strictEqual(api.percent(0, 5), 0);
    assert.strictEqual(api.percent(2, 5), 40);
    assert.strictEqual(api.percent(1, 3), 33);
    assert.strictEqual(api.percent(5, 5), 100);
    assert.strictEqual(api.percent(7, 5), 100); // contacts that match later are deleted too
    assert.strictEqual(api.percent(3, 0), 100);
});

test('wordMatches: the word of the language, case and the spaces at the ends ignored; a part of it is not enough', () => {
    assert.strictEqual(api.wordMatches('EXCLUIR', 'EXCLUIR'), true);
    assert.strictEqual(api.wordMatches('  excluir ', 'EXCLUIR'), true);
    assert.strictEqual(api.wordMatches('delete', 'DELETE'), true);
    assert.strictEqual(api.wordMatches('EXCLUI', 'EXCLUIR'), false);
    assert.strictEqual(api.wordMatches('', 'EXCLUIR'), false);
    assert.strictEqual(api.wordMatches('EXCLUIR!', 'EXCLUIR'), false);
});

test('runBatches: posts until the server says done, reports the progress of every batch but the last, resolves with the total', async () => {
    const answers = [{deleted: 200, done: false}, {deleted: 200, done: false}, {deleted: 50, done: true}];
    const progress = [];
    let posts = 0;

    const deleted = await api.runBatches(() => Promise.resolve(answers[posts++]), (n) => progress.push(n));

    assert.strictEqual(deleted, 450);
    assert.strictEqual(posts, 3);
    assert.deepStrictEqual(progress, [200, 400]);
});

test('runBatches: one batch that is already done does not report progress', async () => {
    const progress = [];

    const deleted = await api.runBatches(() => Promise.resolve({deleted: 3, done: true}), (n) => progress.push(n));

    assert.strictEqual(deleted, 3);
    assert.deepStrictEqual(progress, []);
});

test('runBatches: a failure rejects with what was deleted before it, and posts no more', async () => {
    let posts = 0;
    const boom = new Error('network');

    await assert.rejects(
        api.runBatches(() => (++posts === 1 ? Promise.resolve({deleted: 200, done: false}) : Promise.reject(boom)), () => {}),
        (failure) => failure.done === 200 && failure.error === boom
    );
    assert.strictEqual(posts, 2);
});
