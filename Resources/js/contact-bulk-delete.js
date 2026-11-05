/*
 * The page "Excluir em lote" of the contact list (ContactBulkDeleteController, Resources/views/Contact/bulk_delete.html.twig holds the
 * markup and the texts as data- attributes). The filter bar of the contact list table IS the search: every load of the table (the bar's
 * live search, sorting, paging) brings the total and the search it answered in its response (`contactsTotal`, `contactsSearch`), core hands
 * the response to Mautic.contactBulkDeleteContactsOnLoad, and that total turns the red Excluir button on. The delete posts one batch at a time
 * until the server says it is done, behind core's confirmation modal with a box for the word of the language.
 *
 * The first part is plain functions (tested with node: Tests/js/contact-bulk-delete.test.js); `install` is the glue with the page, covered
 * by the browser spec (scripts/<version>/e2e/tests/contact-bulk-delete.spec.js).
 */
(function (root) {
    'use strict';

    var BAR = '#search-contact-bulk-delete-result';

    function fill(text, vars) {
        Object.keys(vars).forEach(function (key) { text = text.replace('%' + key + '%', vars[key]); });

        return text;
    }

    /** texts: {none, one, many}; many has %count%. */
    function foundText(texts, count) {
        if (count === 0) { return texts.none; }

        return count === 1 ? texts.one : fill(texts.many, {count: count});
    }

    /** The percent of the bar: capped at 100, because contacts that match later are deleted too and `done` can pass the total. */
    function percent(done, total) {
        return total > 0 ? Math.min(100, Math.round(done / total * 100)) : 100;
    }

    /** The word of the language, case and the spaces at the ends ignored. */
    function wordMatches(typed, word) {
        return typed.trim().toUpperCase() === word.toUpperCase();
    }

    /**
     * Posts batches until the server says `done`. post() gives a promise of {deleted, done}; onProgress(deletedSoFar) runs after every
     * batch that is not the last. Resolves with the number deleted; rejects with {done: deletedSoFar, error}.
     */
    function runBatches(post, onProgress) {
        return new Promise(function (resolve, reject) {
            var deleted = 0;

            function next() {
                post().then(function (data) {
                    deleted += data.deleted;
                    if (data.done) { return resolve(deleted); }
                    onProgress(deleted);
                    next();
                }, function (error) { reject({done: deleted, error: error}); });
            }

            next();
        });
    }

    function install($, Mautic) {
        var page = null; // what the page that is open holds: its elements, the total of the counted search and that search

        function el(id) { return document.getElementById(id); }

        // the button is part of the table, which is loaded again at the end: always looked up when used
        function runBtn() { return el('contact-bulk-delete-run'); }

        function barText() {
            var field = document.querySelector(BAR);

            return field ? field.value.trim() : '';
        }

        function setRunEnabled(enabled) {
            if (runBtn()) { runBtn().disabled = !enabled; }
        }

        // The one alert slot, above the guide text: the count (red, to catch the eye), a failure, the result of the delete.
        function showBanner(type, text) {
            var banner = el('contact-bulk-delete-banner');
            banner.className = 'alert alert-' + type;
            banner.textContent = text;
            banner.hidden = false;
        }

        function hideBanner() { el('contact-bulk-delete-banner').hidden = true; }

        function text(name) { return page.box.getAttribute('data-' + name); }

        function postBatch(search) {
            var options = {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.mauticAjaxCsrf, // core's ajax calls send it; the server refuses a POST without it
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'search=' + encodeURIComponent(search)
            };

            return fetch(text('batch-url'), options).then(function (response) {
                if (!response.ok) { throw new Error(response.status); }

                return response.json();
            });
        }

        // The table of the contact list with its bar. Its first load keeps the search in the session; the bar's live search, sorting and
        // paging read it there.
        function loadTable() {
            $.get(text('table-url'), {search: ''}, function (response) {
                response.target = '#contact-bulk-delete-result';
                Mautic.processPageContent(response);
            }).fail(function (request, status, error) { Mautic.processAjaxError(request, status, error); });
        }

        // The delete is on only for the search whose total came with the table: any change of the bar turns it off until the next answer.
        function reset() {
            setRunEnabled(false);
            page.searched = '';
        }

        // Core calls this after every load of the table, with the response of the server.
        function onTable(response) {
            if (!page || !response || typeof response.contactsSearch !== 'string') { return; }
            var search = barText();
            if (response.contactsSearch.trim() !== search) { return; } // the answer of an older text: the newer one is on its way
            if (!search) { return; } // (the button is already off: the change of the text turned it off)
            page.total = response.contactsTotal;
            page.searched = search;
            showBanner(page.total > 0 ? 'danger' : 'info', foundText({none: text('found-none'), one: text('found-one'), many: text('found')}, page.total));
            $(runBtn()).data('message', fill(text('confirm'), {count: page.total}));
            setRunEnabled(page.total > 0);
        }

        function setProgress(done) {
            var value = percent(done, page.total);
            var bar = el('contact-bulk-delete-bar');
            bar.style.width = value + '%';
            bar.setAttribute('aria-valuenow', value);
            bar.textContent = value + '%';
            el('contact-bulk-delete-count').textContent = fill(text('progress'), {done: done, total: page.total});
        }

        function stop(type, message) {
            el('contact-bulk-delete-progress').hidden = true;
            el('contact-bulk-delete-form').hidden = false;
            loadTable(); // the table again, from an empty search, with what is left
            showBanner(type, message);
        }

        function run() {
            var search = page.searched;
            reset();
            // the form (guide, table with its bar and button) gives its place to the progress: the eye has one thing to follow
            hideBanner();
            el('contact-bulk-delete-form').hidden = true;
            el('contact-bulk-delete-progress').hidden = false;
            setProgress(0);

            runBatches(function () { return postBatch(search); }, setProgress).then(function (deleted) {
                var message = fill(text('done'), {count: deleted});
                stop('success', message);
                Mautic.setFlashes(Mautic.addInfoFlashMessage(message));
            }, function (failure) { stop('danger', fill(text('error'), {done: failure.done})); });
        }

        // the confirm button of core's confirmation modal calls this by name
        Mautic.patchesBulkDeleteRun = function () {
            Mautic.dismissConfirmation();
            if (page && page.searched) { run(); }
        };

        // core's modal has the question and the buttons; the box goes between them and keeps the confirm button off until the word is typed
        function askTheWord() {
            Mautic.showConfirmation(runBtn());
            var content = $('.confirmation-modal').last().find('.modal-content').first();
            var confirmBtn = content.find('#confirm');
            var typed = $('<input type="text" class="form-control" autocomplete="off" id="contact-bulk-delete-word">');
            var group = $('<div class="form-group pa-md mb-0 text-left" />')
                .append($('<label class="control-label" for="contact-bulk-delete-word" />').text(text('confirm-type')))
                .append(typed);
            content.children('.modal-header').after(group);
            confirmBtn.prop('disabled', true);
            typed.on('input', function () { confirmBtn.prop('disabled', !wordMatches(typed.val(), text('confirm-word'))); });
            typed.on('keydown', function (event) {
                if (event.key === 'Enter' && !confirmBtn.prop('disabled')) { confirmBtn.click(); }
            });
            typed.trigger('focus');
        }

        // core calls this when the page itself has loaded (mauticContent patchesContactBulkDelete)
        Mautic.patchesContactBulkDeleteOnLoad = function () {
            var box = el('contact-bulk-delete');
            if (!box) { return; }
            page = {box: box, total: 0, searched: ''};
            $(box).on('click', '#contact-bulk-delete-run', askTheWord);
            // a change of the text: the old count and the delete go; the new total comes with the table the live search loads
            $(box).on('input', BAR, function () { reset(); hideBanner(); });
            loadTable();
        };

        // core calls this after every load of the table (mauticContent contactBulkDeleteContacts), with the response
        Mautic.contactBulkDeleteContactsOnLoad = function (container, response) { onTable(response); };
    }

    var api = {fill: fill, foundText: foundText, percent: percent, wordMatches: wordMatches, runBatches: runBatches, install: install};

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.mauticPatchesContactBulkDeleteApi = api;
    }
})(typeof window !== 'undefined' ? window : this);
