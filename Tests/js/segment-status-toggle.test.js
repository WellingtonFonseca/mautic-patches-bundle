// The logic of the segment status toggle (Resources/js/segment-status-toggle.js): what the answer of the server says, and what the
// label and the menu item show for it. The DOM part is checked in the browser.
//
//   docker exec mautic-mautic_web-1 sh -c "cd /var/www/html/docroot/plugins/MauticPatchesBundle && node --test 'Tests/js/*.test.js'"
'use strict';

const test   = require('node:test');
const assert = require('node:assert');
const api    = require('../../Resources/js/segment-status-toggle.js');

const cfg = {active: 'Ativo', inactive: 'Inativo', activate: 'Ativar', deactivate: 'Desativar'};

test('statusOf: the data-status of the icon in the answer', () => {
    assert.strictEqual(api.statusOf({statusHtml: '<i class="ri-toggle-fill" data-status="published"></i>'}), 'published');
    assert.strictEqual(api.statusOf({statusHtml: '<i class="ri-toggle-line" data-status="unpublished"></i>'}), 'unpublished');
});

test('statusOf: expired, pending, no icon or no answer are "not active"', () => {
    assert.strictEqual(api.statusOf({statusHtml: '<i data-status="expired"></i>'}), 'unpublished');
    assert.strictEqual(api.statusOf({statusHtml: '<i data-status="pending"></i>'}), 'unpublished');
    assert.strictEqual(api.statusOf({statusHtml: '<i></i>'}), 'unpublished');
    assert.strictEqual(api.statusOf({}), 'unpublished');
    assert.strictEqual(api.statusOf(null), 'unpublished');
});

test('view: an active segment is a green label and the menu offers to deactivate it', () => {
    assert.deepStrictEqual(api.view('published', cfg), {
        labelClass: 'label-success', labelText: 'Ativo', action: 'deactivate', actionText: 'Desativar', actionIcon: 'ri-close-circle-line',
    });
});

test('view: an inactive segment is a red label and the menu offers to activate it', () => {
    assert.deepStrictEqual(api.view('unpublished', cfg), {
        labelClass: 'label-danger', labelText: 'Inativo', action: 'activate', actionText: 'Ativar', actionIcon: 'ri-checkbox-circle-line',
    });
});
