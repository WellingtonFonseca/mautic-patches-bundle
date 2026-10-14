// Runs the REAL script that CampaignPreviewSourceSubscriber puts on the campaign page (the Preview tab names the
// segments of the contact source; the script rewrites them as "PREFIX - Name"), against a small fake document.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const path   = require('node:path');
const vm     = require('node:vm');
const {execFileSync} = require('node:child_process');

const AUTOLOAD = process.env.AUTOLOAD || '/var/www/html/vendor/autoload.php';

function script(oldNames, newNames) {
    return execFileSync('php', ['-r',
        `require ${JSON.stringify(AUTOLOAD)}; require ${JSON.stringify(path.resolve(__dirname, '../../EventListener/CampaignPreviewSourceSubscriber.php'))}; echo MauticPlugin\\MauticPatchesBundle\\EventListener\\CampaignPreviewSourceSubscriber::script(${JSON.stringify(oldNames)}, ${JSON.stringify(newNames)});`,
    ], {encoding: 'utf8'});
}

// A node: the icon element, then the text node with the names (as core's Source/_index.html.twig draws it).
function page(text) {
    const icon = {nodeType: 1, nodeValue: null, nextSibling: null};
    const textNode = {nodeType: 3, nodeValue: text, nextSibling: null};
    icon.nextSibling = textNode;
    const span = {firstChild: icon};
    const selectors = [];
    const document = {
        readyState: 'complete',
        querySelector(sel) { selectors.push(sel); return sel.includes('#CampaignEvent_lists') ? span : null; },
        addEventListener() {},
    };

    return {document, textNode, selectors};
}

test('the names are replaced by the prefixed ones', () => {
    const p = page(' Onboarding, Livre ');
    vm.runInNewContext(script('Onboarding, Livre', 'Compras - Onboarding, Livre'), {document: p.document});

    assert.strictEqual(p.textNode.nodeValue, ' Compras - Onboarding, Livre ');
    assert.ok(p.selectors.every((s) => s.includes('#preview-container')), 'only inside the preview tab');
});

test('other text is left alone', () => {
    const p = page(' Outro segmento ');
    vm.runInNewContext(script('Onboarding', 'Compras - Onboarding'), {document: p.document});

    assert.strictEqual(p.textNode.nodeValue, ' Outro segmento ');
});

test('running it twice does not prefix twice', () => {
    const p = page('Onboarding');
    const js = script('Onboarding', 'Compras - Onboarding');
    vm.runInNewContext(js, {document: p.document});
    vm.runInNewContext(js, {document: p.document});

    assert.strictEqual(p.textNode.nodeValue, 'Compras - Onboarding');
});

test('a page without the node does nothing', () => {
    const document = {readyState: 'complete', querySelector: () => null, addEventListener() {}};
    vm.runInNewContext(script('A', 'B - A'), {document});
});
