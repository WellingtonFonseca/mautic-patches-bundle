/*
 * Core's confirmation (Mautic.showConfirmation, 9.modals.js: delete a contact, segment, campaign, email, deactivate a campaign, ...) puts
 * the question in the modal header and its two buttons in a `.modal-body.text-center`, with Cancel as the (blue) primary. Every other modal
 * of the project has its buttons in the footer (`.modal-footer .modal-form-buttons`, see ModalFormButtonsSubscriber), the action first and
 * the transparent Cancel (`btn-tertiary btn-cancel`) last. This wraps core's function and moves the buttons there after core built the
 * modal; core's own click handlers stay on the same button elements, so the behavior does not change.
 *
 * Core's function is looked up when it is called, so it is wrapped as soon as it exists (the script may run before core's).
 */
(function (root) {
    'use strict';

    /** Moves the buttons of a built confirmation modal into a footer: the action (#confirm) first, Cancel last. */
    function moveToFooter($, modal) {
        var content = $(modal).find('.modal-content').first();
        var body = content.children('.modal-body');
        if (!content.length || !body.length || content.children('.modal-footer').length) { return false; }

        var confirm = body.find('#confirm');
        var cancel = body.find('button').not(confirm).first();
        // the margins core puts on the action button would make the footer buttons uneven
        confirm.css({marginLeft: '', marginRight: ''});
        cancel.removeClass('btn-primary').addClass('btn-tertiary btn-cancel').append($('<i />').addClass('ri-close-line'));
        // the icon at the end of the text, like the other footers: a bin for a destructive action, a check for any other
        confirm.append($('<i />').addClass(confirm.hasClass('btn-danger') ? 'ri-delete-bin-line' : 'ri-check-line'));

        var buttons = $('<div />').addClass('modal-form-buttons').append(confirm).append(cancel);
        body.replaceWith($('<div />').addClass('modal-footer').append(buttons));

        return true;
    }

    function wrap($, Mautic) {
        var original = Mautic && Mautic.showConfirmation;
        if (typeof original !== 'function' || original.mauticPatchesFooter) { return false; }

        var wrapped = function () {
            var result = original.apply(this, arguments);
            // core appends the modal to the body and opens it; the last one is the one just built (none when a precheck refused)
            var modals = $('.confirmation-modal');
            if (modals.length) { moveToFooter($, modals.last()); }

            return result;
        };
        wrapped.mauticPatchesFooter = true;
        Mautic.showConfirmation = wrapped;

        return true;
    }

    function install($, Mautic) {
        if (!$ || !Mautic) { return; }
        if (wrap($, Mautic)) { return; }
        var tries = 0;
        var timer = setInterval(function () {
            if (wrap($, Mautic) || ++tries > 100) { clearInterval(timer); }
        }, 50);
    }

    var api = {install: install, wrap: wrap, moveToFooter: moveToFooter};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesConfirmationFooterApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
