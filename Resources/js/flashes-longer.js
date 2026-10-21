/*
 * The notifications of Mautic (the faint at the top right) go away by themselves after 4 seconds, too soon to be noticed and read, mostly
 * the error ones ("Este segmento está sendo usado na campanha ..., verifique antes de retirar do ar"). Core's Mautic.setFlashes
 * (1.core.js) appends the flashes and, unless told not to, removes each one after 4000 ms. This wraps it: core is asked NOT to
 * auto-close and the notifications stay longer, by their weight: 10 s a notice, 15 s a warning, 20 s an error. They stay while the mouse
 * is over them (they can be read with calm) and go 4 s after it leaves. The close button still closes at once. A caller that asks for no
 * auto-close (autoClose = false) is still obeyed.
 *
 * Core's function is looked up when it is called, so it is wrapped as soon as it exists (the script may run before core's).
 */
(function (root) {
    'use strict';

    var STAY = {notice: 10000, warning: 15000, error: 20000};
    var AFTER_HOVER = 4000;

    /** How long a notification stays, by its class (alert-growl, alert-growl--warning, alert-growl--error). */
    function duration(className) {
        if (/alert-growl--error|alert-danger/.test(className)) { return STAY.error; }
        if (/alert-growl--warning|alert-warning/.test(className)) { return STAY.warning; }

        return STAY.notice;
    }

    /** Core's way out: fade and slide up, then remove. */
    function dismiss($, el) {
        $(el).fadeTo(500, 0).slideUp(500, function () { $(this).remove(); });
    }

    function schedule($, el) {
        var timer = null;
        function arm(ms) { clearTimeout(timer); timer = setTimeout(function () { dismiss($, el); }, ms); }
        $(el).on('mouseenter', function () { clearTimeout(timer); })
             .on('mouseleave', function () { arm(AFTER_HOVER); });
        arm(duration(el.className));
    }

    function wrap($, Mautic) {
        var original = Mautic && Mautic.setFlashes;
        if (typeof original !== 'function' || original.mauticPatchesLonger) { return false; }

        var wrapped = function (flashes, autoClose) {
            var before = $('#flashes .alert').toArray();
            // core appends them, takes alert-new off them and, with autoClose false, leaves them alone
            var result = original.call(this, flashes, false);
            if (autoClose !== false) {
                $('#flashes .alert').each(function () {
                    if (before.indexOf(this) === -1 && !this.mauticPatchesTimed) {
                        this.mauticPatchesTimed = true;
                        schedule($, this);
                    }
                });
            }

            return result;
        };
        wrapped.mauticPatchesLonger = true;
        Mautic.setFlashes = wrapped;

        return true;
    }

    function install($, Mautic) {
        if (!$ || !Mautic) { return; }
        if (wrap($, Mautic)) { return; }
        // core is not loaded yet: wrapped as soon as it is
        var tries = 0;
        var timer = setInterval(function () {
            if (wrap($, Mautic) || ++tries > 100) { clearInterval(timer); }
        }, 50);
    }

    var api = {install: install, wrap: wrap, duration: duration, STAY: STAY, AFTER_HOVER: AFTER_HOVER};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesFlashesLongerApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
