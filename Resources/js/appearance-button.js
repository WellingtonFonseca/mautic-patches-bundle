/*
 * A sun / moon button left of the notification bell opens the Appearance modal (the theme cards, the body comes from
 * AppearanceModalController). Core 7 keeps the theme as a preference of the user: base.html.twig renders `theme` on <html> from it on
 * every page and the profile form saves it. The modal has no form, so a click on a card sets the attribute of <html> at once and
 * posts the theme to the plugin, which saves the preference. The icon is a moon on a light theme (click: go dark) and a sun on a dark one.
 *
 * The radios are marked when the modal opens from the attribute of <html>: the markup does not know the user's theme.
 */
(function (root) {
    'use strict';

    var DARK = ['dark', 'solarized-dark', 'freire'];
    var DEFAULTS = {theme: 'light'};
    var RADIOS = '.mautic-patches-appearance input[data-attribute-toggle]';

    function isDark(theme) { return DARK.indexOf(theme) !== -1; }

    function iconFor(theme) { return isDark(theme) ? 'ri-sun-line' : 'ri-moon-line'; }

    function currentChoice(doc, name) {
        return doc.documentElement.getAttribute(name) || DEFAULTS[name];
    }

    function markRadios($, doc) {
        $(RADIOS).each(function () {
            this.checked = this.getAttribute('data-attribute-value') === currentChoice(doc, this.getAttribute('data-attribute-toggle'));
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

    function addButton(Mautic, doc, config) {
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

    /** Applies the card at once and saves it; the page keeps the new theme even when the save is slow or fails (the error is core's). */
    function choose($, doc, config, name, value) {
        doc.documentElement.setAttribute(name, value);
        var data = {};
        data[name] = value;
        $.post(config.saveUrl, data);
    }

    function install($, Mautic, doc, config) {
        // the script is injected on every page header: one set of handlers only
        if (doc.mauticPatchesAppearanceInstalled) { return; }
        doc.mauticPatchesAppearanceInstalled = true;

        var start = function () {
            addButton(Mautic, doc, config);
            new MutationObserver(function () { showIcon(doc); }).observe(doc.documentElement, {attributes: true, attributeFilter: ['theme']});
        };
        if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', start); } else { start(); }

        $(doc).on('change', RADIOS, function () {
            choose($, doc, config, this.getAttribute('data-attribute-toggle'), this.getAttribute('data-attribute-value'));
        });
        $(doc).on('shown.bs.modal', '#MauticSharedModal', function () { markRadios($, doc); });
    }

    var api = {install: install, iconFor: iconFor, currentChoice: currentChoice, choose: choose};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesAppearanceApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
