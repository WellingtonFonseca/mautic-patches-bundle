/*
 * The plugin tiles of Settings > Plugins stay in the state they had when the page opened: after the settings of a plugin are saved with
 * the "Ativo" switch changed, the tile of that plugin is still colored (it was turned off) or still gray (it was turned on) until the page
 * is reloaded. Core's server answers right: the save-and-close answer is {name, enabled, closeModal}. But the answer says
 * mauticContent = "integrationConfig", so core runs Mautic.integrationConfigOnLoad(container, response), which does not look at it; the
 * function that paints the tile (Mautic.integrationOnLoad: `integration-disabled` on `.integration-<name>`) only runs for the answers
 * of the plugin list itself. This wraps integrationConfigOnLoad: after it runs, an answer that has a name and `enabled` paints the tile.
 * Any plugin, not only ours.
 */
(function (root) {
    'use strict';

    /** Puts core's `integration-disabled` on the tile of the plugin the answer is about, or takes it off. */
    function paint($, response) {
        if (!response || !response.name || typeof response.enabled === 'undefined') { return false; }
        $('.integration-' + response.name).toggleClass('integration-disabled', !Number(response.enabled));

        return true;
    }

    /** Wraps core's Mautic.integrationConfigOnLoad once; false while core's script is not there yet. */
    function wrap($, Mautic) {
        if (!Mautic || typeof Mautic.integrationConfigOnLoad !== 'function') { return false; }
        if (Mautic.integrationConfigOnLoad.patchesTileStatus) { return true; }

        var original = Mautic.integrationConfigOnLoad;
        Mautic.integrationConfigOnLoad = function (container, response) {
            var result = original.apply(this, arguments);
            paint($, response);

            return result;
        };
        Mautic.integrationConfigOnLoad.patchesTileStatus = true;

        return true;
    }

    function install($, Mautic) {
        if (!$ || wrap($, Mautic)) { return; }
        // the page scripts of core may come after this one: look again for a moment
        var tries = 0;
        var timer = setInterval(function () {
            if (wrap($, Mautic) || ++tries > 100) { clearInterval(timer); }
        }, 50);
    }

    var api = {install: install, wrap: wrap, paint: paint};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesPluginTileStatusApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
