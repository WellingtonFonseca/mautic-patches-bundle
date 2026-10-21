/*
 * The circles of the nodes of the campaign builder sit 70% outside the node (30% over it). Core centres each circle on the edge
 * of the node (half and half); the circle is 20 px wide, so its centre goes 4 px away from the node.
 *
 * Core places a circle with an anchor [x, y, directionX, directionY, offsetX, offsetY] kept in
 * Mautic.campaignEndpointDefinitions[name].anchors, read when the nodes get their circles (campaignBuilderRegisterAnchors).
 * The offsets are moved once, before that: the circle of the top goes up, the ones of the bottom (the plain one, yes, no, the
 * contact source) go down, the left one of the contact source goes left and the right one goes right.
 * The shift is a plain function, tested in Node (Tests/js/campaign-endpoints-outside.test.js).
 */
(function (root) {
    'use strict';

    var OUT = 4;

    // which offset of the anchor moves, and to which side, for each kind of circle (its direction decides it)
    function shiftAnchor(anchor, out) {
        var moved = anchor.slice();
        var dx = anchor[2];
        var dy = anchor[3];
        if (dy !== 0) {
            moved[5] = (anchor[5] || 0) + dy * out;
        } else if (dx !== 0) {
            moved[4] = (anchor[4] || 0) + dx * out;
        }

        return moved;
    }

    function shiftDefinitions(definitions, out) {
        Object.keys(definitions).forEach(function (name) {
            if (definitions[name].anchors) {
                definitions[name].anchors = shiftAnchor(definitions[name].anchors, out);
            }
        });
    }

    var api = {shiftAnchor: shiftAnchor, shiftDefinitions: shiftDefinitions, OUT: OUT};

    function install(Mautic) {
        if (root.mauticPatchesEndpointsOutside) { return; }

        // campaign.js may load after this script: tried on every page load until the definitions exist; done once
        function run() {
            if (root.mauticPatchesEndpointsOutside || !Mautic.campaignEndpointDefinitions) { return; }
            root.mauticPatchesEndpointsOutside = true;
            shiftDefinitions(Mautic.campaignEndpointDefinitions, OUT);
        }

        var original = Mautic.onPageLoad;
        Mautic.onPageLoad = function () { var result = original.apply(this, arguments); run(); return result; };
        run();
    }

    api.install = install;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesEndpointsOutsideApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
