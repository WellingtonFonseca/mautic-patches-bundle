/*
 * A sun / moon button left of the notification bell opens the Appearance modal (accent colors and theme cards, the body comes from
 * AppearanceModalController). Core keeps a choice in two places: the attribute of <html> (`theme`, `accent`) and localStorage
 * (`m-toggle-setting-<name>`), and applies it only when the Save of the profile form is clicked. The modal has no form, so a click on a
 * radio of it writes both at once. The icon is a moon on a light theme (click: go dark) and a sun on a dark one.
 *
 * The radios are marked when the modal opens, from storage or from <html>: the markup does not know the browser's choice.
 */
(function (root) {
    'use strict';

    var PREFIX = 'm-toggle-setting-';
    var DARK = ['dark', 'solarized-dark', 'freire'];
    var DEFAULTS = {theme: 'light', accent: '01'};
    var RADIOS = '.mautic-patches-appearance input[data-attribute-toggle]';

    function isDark(theme) { return DARK.indexOf(theme) !== -1; }

    function iconFor(theme) { return isDark(theme) ? 'ri-sun-line' : 'ri-moon-line'; }

    function currentChoice(doc, storage, name) {
        var saved = null;
        try { saved = storage.getItem(PREFIX + name); } catch (e) { saved = null; }

        return saved || doc.documentElement.getAttribute(name) || DEFAULTS[name];
    }

    function applyChoice(doc, storage, name, value) {
        doc.documentElement.setAttribute(name, value);
        try { storage.setItem(PREFIX + name, value); } catch (e) { /* blocked storage: the choice lasts until the next page load */ }
    }

    function markRadios($, doc, storage) {
        $(RADIOS).each(function () {
            var name = this.getAttribute('data-attribute-toggle');
            this.checked = this.getAttribute('data-attribute-value') === currentChoice(doc, storage, name);
        });
    }

    function buildButton(doc, config) {
        var li = doc.createElement('li');
        li.className = 'dropdown-custom patches-appearance-button';
        var a = doc.createElement('a');
        a.className = 'dropdown-notification';
        a.setAttribute('href', config.url);
        a.setAttribute('data-toggle', 'ajaxmodal');
        a.setAttribute('data-target', '#MauticSharedModal');
        a.setAttribute('data-header', config.label);
        a.setAttribute('title', config.label);
        a.setAttribute('aria-label', config.label);
        var icon = doc.createElement('span');
        icon.className = 'ri-xl';
        a.appendChild(icon);
        li.appendChild(a);

        return li;
    }

    function showIcon(doc) {
        var icon = doc.querySelector('.patches-appearance-button span');
        if (icon) { icon.className = iconFor(doc.documentElement.getAttribute('theme')) + ' ri-xl'; }
    }

    function addButton($, Mautic, doc, config) {
        var bar = doc.querySelector('.navbar-right');
        if (!bar || doc.querySelector('.patches-appearance-button')) { return false; }

        var li = buildButton(doc, config);
        bar.insertBefore(li, bar.firstChild);
        showIcon(doc);
        // core binds its ajaxmodal handler to the links of the page it loads, not to this one
        li.querySelector('a').addEventListener('click', function (event) {
            event.preventDefault();
            Mautic.ajaxifyModal(this, event);
        });

        return true;
    }

    function install($, Mautic, doc, storage, config) {
        if (!$ || !Mautic || !doc) { return; }

        var start = function () {
            addButton($, Mautic, doc, config);
            new MutationObserver(function () { showIcon(doc); }).observe(doc.documentElement, {attributes: true, attributeFilter: ['theme']});
        };
        if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', start); } else { start(); }

        $(doc).on('change', RADIOS, function () {
            applyChoice(doc, storage, this.getAttribute('data-attribute-toggle'), this.getAttribute('data-attribute-value'));
        });
        $(doc).on('shown.bs.modal', '#MauticSharedModal', function () { markRadios($, doc, storage); });
    }

    var api = {install: install, iconFor: iconFor, isDark: isDark, currentChoice: currentChoice, applyChoice: applyChoice, buildButton: buildButton};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesAppearanceApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
