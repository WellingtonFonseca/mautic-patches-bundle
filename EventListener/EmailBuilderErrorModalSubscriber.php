<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The modal of the email builder (GrapesJS) that says "Não foi possível solicitar sua ação" when the check icon (apply
 * changes) finds validation errors of the email form. The plugin builds it with GrapesJS' own modal (`.gjs-mdl-dialog`):
 * a red title, a white "Close" button in English and none of Mautic's look.
 *
 * Here it gets the look of Mautic's modals, the one of the segment details modal: the modal background and text,
 * the title in the text colour at the size of `.modal-title`, and a `.modal-footer .modal-form-buttons` with one
 * `btn-tertiary btn-cancel` "Fechar" with the ri-close-line icon. Only that modal is touched: the script waits for a
 * `.gjs-mdl-dialog` that has the plugin's `.panel-footer` and swaps that footer for the Mautic one, whose button clicks
 * GrapesJS' own close control (so the editor closes the modal the way it always does). Other GrapesJS modals keep theirs.
 *
 * The errors come from the plugin as one sentence, "... (field: message, field: message) ..."; the script turns them into a
 * list, one item per message and without the field's technical name. Each message already says which tab fixes it (the
 * sender on Advanced, the n8n variables on their tab), so the list tells the person where to go.
 *
 * Injected on 'page.header.left' like the other style/script subscribers.
 */
class EmailBuilderErrorModalSubscriber implements EventSubscriberInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => 'injectViewCustomContent',
        ];
    }

    public function injectViewCustomContent(CustomContentEvent $customContentEvent): void
    {
        if ('page.header.left' !== $customContentEvent->getContext()) {
            return;
        }

        $customContentEvent->addContent('<style>'.self::css().'</style><script>'.self::script($this->translator->trans('mautic.core.close')).'</script>');
    }

    public static function css(): string
    {
        return ':root .gjs-mdl-dialog.mautic-patches-gjs-error{background:var(--background);color:var(--text-primary);border:1px solid transparent;border-radius:var(--border-radius-lg);box-shadow:rgb(100 100 111 / .24) 0 7px 20px 0}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .gjs-mdl-header{background:transparent;border:0;padding:var(--spacing-05)}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .gjs-mdl-title h4{margin:0;color:var(--text-primary);font-size:17px;font-weight:400;line-height:1.3856}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .gjs-mdl-btn-close{color:var(--text-primary);opacity:.4;font-size:21px;line-height:1}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .gjs-mdl-content{background:transparent;border:0;padding:0;color:var(--text-primary)}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .panel-body{padding:0 var(--spacing-05) var(--spacing-05);font-size:14px}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .panel-body p{margin:0 0 var(--spacing-03)}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .mautic-patches-gjs-errors{margin:0 0 var(--spacing-03);padding-left:var(--spacing-06)}'
            .':root .gjs-mdl-dialog.mautic-patches-gjs-error .modal-footer{padding:var(--spacing-05);border:0}';
    }

    public static function script(string $closeLabel): string
    {
        return '(function(){'
            .'if(window.mauticPatchesEmailBuilderErrorModal){return;}'
            // "<intro> (key: message, key: message) <outro>" is the sentence core's builder script makes from the form's errors.
            .'function parse(text){'
            .'var m=/^([^(]*)\\((.*)\\)\\s*([^()]*)$/.exec(text);'
            .'if(!m){return null;}'
            .'var items=m[2].split(/, (?=[\\w.\\[\\]-]+: )/).map(function(i){return i.replace(/^[\\w.\\[\\]-]+: /,"").trim();}).filter(Boolean);'
            .'return items.length?{intro:m[1].trim(),items:items,outro:m[3].trim()}:null;}'
            .'window.mauticPatchesEmailBuilderErrorModal={parse:parse};'
            .'var label='.json_encode($closeLabel, JSON_UNESCAPED_UNICODE).';'
            .'function list(body){'
            .'var parsed=parse(body.textContent);'
            .'if(!parsed){return;}'
            .'while(body.firstChild){body.removeChild(body.firstChild);}'
            .'var p=document.createElement("p");p.appendChild(document.createTextNode(parsed.intro));body.appendChild(p);'
            .'var ul=document.createElement("ul");ul.className="mautic-patches-gjs-errors";'
            .'parsed.items.forEach(function(t){var li=document.createElement("li");li.appendChild(document.createTextNode(t));ul.appendChild(li);});'
            .'body.appendChild(ul);'
            .'if(parsed.outro){var o=document.createElement("p");o.appendChild(document.createTextNode(parsed.outro));body.appendChild(o);}}'
            .'function fix(){'
            .'var dialogs=document.querySelectorAll(".gjs-mdl-dialog:not(.mautic-patches-gjs-error)");'
            .'for(var i=0;i<dialogs.length;i++){'
            .'var d=dialogs[i],old=d.querySelector(".panel-footer");'
            .'if(!old){continue;}'
            .'d.classList.add("mautic-patches-gjs-error");'
            .'var body=d.querySelector(".panel-body");if(body){list(body);}'
            .'var footer=document.createElement("div");footer.className="modal-footer";'
            .'var box=document.createElement("div");box.className="modal-form-buttons";'
            .'var btn=document.createElement("button");btn.type="button";btn.className="btn btn-tertiary btn-cancel";'
            .'btn.appendChild(document.createTextNode(label));'
            .'var icon=document.createElement("i");icon.className="ri-close-line";btn.appendChild(icon);'
            .'btn.addEventListener("click",function(){var x=d.querySelector("[data-close-modal]");if(x){x.click();}});'
            .'box.appendChild(btn);footer.appendChild(box);old.parentNode.replaceChild(footer,old);'
            .'}}'
            .'new MutationObserver(fix).observe(document.documentElement,{childList:true,subtree:true});'
            .'})();';
    }
}
