// Runs the REAL script that CampaignTitlePrefixSubscriber puts on the campaign page (the page title and the browser
// tab title read "PREFIX - Name"), against a small fake document.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script(oldTitle, newTitle) {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/CampaignTitlePrefixSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignTitlePrefixSubscriber::script(${JSON.stringify(oldTitle)}, ${JSON.stringify(newTitle)});`,
    ], {encoding: 'utf8'});
}

function page(heading, title) {
    const h1 = {textContent: heading};
    const document = {
        readyState: 'complete',
        title,
        querySelector: (sel) => (sel === 'h1.page-header-title' ? h1 : null),
        addEventListener() {},
    };

    return {document, h1};
}

test('the page title and the tab title get the prefix', () => {
    const p = page('Onboarding', 'Onboarding | Mautic');
    vm.runInNewContext(script('Onboarding', 'Compras - Onboarding'), {document: p.document});

    assert.strictEqual(p.h1.textContent, 'Compras - Onboarding');
    assert.strictEqual(p.document.title, 'Compras - Onboarding | Mautic');
});

test('running it twice does not prefix twice', () => {
    const p = page('Onboarding', 'Onboarding | Mautic');
    const js = script('Onboarding', 'Compras - Onboarding');
    vm.runInNewContext(js, {document: p.document});
    vm.runInNewContext(js, {document: p.document});

    assert.strictEqual(p.h1.textContent, 'Compras - Onboarding');
    assert.strictEqual(p.document.title, 'Compras - Onboarding | Mautic');
});

test('a heading that is not the campaign name is left alone', () => {
    const p = page('Outra coisa', 'Outra coisa | Mautic');
    vm.runInNewContext(script('Onboarding', 'Compras - Onboarding'), {document: p.document});

    assert.strictEqual(p.h1.textContent, 'Outra coisa');
    assert.strictEqual(p.document.title, 'Outra coisa | Mautic');
});

test('a page without the heading does nothing', () => {
    const document = {readyState: 'complete', title: 'x', querySelector: () => null, addEventListener() {}};
    vm.runInNewContext(script('A', 'B - A'), {document});
    assert.strictEqual(document.title, 'x');
});
