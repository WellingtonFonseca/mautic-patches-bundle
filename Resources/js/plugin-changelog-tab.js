/*
 * The change log of the plugin as its own tab on Settings > Plugins > Mautic Patches. Core draws the tabs of that modal by itself and
 * gives a plugin no way to add one, so Resources/views/Integration/_changelog.html.twig (the 'custom' note of the first tab) marks the
 * block with data-onload-callback="patchesChangelogTab" and this moves it into a new tab once the modal is up. Without the script the
 * block just stays, with its title, under the fields of the first tab.
 */
(function (root) {
    'use strict';

    var PANE_ID = 'patches-changelog-container';

    /** Core calls this (Mautic.<name>) with the block when the modal's content is up. */
    function moveToTab($, element) {
        var $block = $(element);
        var $content = $block.closest('.tab-content');
        // core draws the tab list just before the form that holds the tab content
        var $tabs = $content.closest('form').prev('ul.nav-tabs');

        if (!$content.length || !$tabs.length || $('#' + PANE_ID).length) { return false; }

        $block.find('.patches-changelog-title').remove();

        $('<li id="patches-changelog-tab"></li>')
            .append($('<a role="tab" data-toggle="tab"></a>').attr('href', '#' + PANE_ID).text($block.attr('data-tab-label')))
            .appendTo($tabs);

        $('<div class="tab-pane fade bdr-w-0"></div>').attr('id', PANE_ID).append($block).appendTo($content);

        return true;
    }

    function install($, Mautic) {
        if (!$ || !Mautic) { return; }
        Mautic.patchesChangelogTab = function (element) { moveToTab($, element); };
    }

    var api = {install: install, moveToTab: moveToTab, PANE_ID: PANE_ID};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesChangelogTabApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
