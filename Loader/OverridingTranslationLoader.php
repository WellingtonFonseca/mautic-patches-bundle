<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Loader;

use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Core's translation loader reads the bundles' Translations first and the downloaded packs (translations/<locale>) after them, so a
 * plugin's own `Translations/<locale>/messages.ini` can never replace a text of the pack. This loader reads core's catalogue and then
 * puts on top of it the texts of Resources/translation-overrides/<locale>/<domain>.ini (messages, flashes, validators...), for the texts
 * the pack gets wrong, with a placeholder named differently, malformed or lost: pt_BR `mautic.core.close_back` names its placeholder
 * %alvo% while core's templates fill %target%, so every "Back to <list>" button read "Voltar ao %alvo%"; `%contato%` for %contact%,
 * `%ccount%` for %count%, `%id#`... Only the keys of the files are touched. Registered under core's service id in Config/services.php.
 */
class OverridingTranslationLoader implements LoaderInterface
{
    public const OVERRIDES_DIR = __DIR__.'/../Resources/translation-overrides';

    /**
     * Core's loader is final on this version: it is wrapped, not extended.
     */
    public function __construct(private LoaderInterface $core)
    {
    }

    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $catalogue = $this->core->load($resource, $locale, $domain);

        foreach (self::overrides($locale) as $domainOfTheFile => $messages) {
            $catalogue->add($messages, $domainOfTheFile);
        }

        return $catalogue;
    }

    /**
     * The overrides of a locale, by domain (the name of the file: messages, flashes, validators...).
     *
     * @return array<string, array<string, string>>
     */
    public static function overrides(string $locale, string $dir = self::OVERRIDES_DIR): array
    {
        $found = [];
        foreach (glob($dir.'/'.$locale.'/*.ini') ?: [] as $file) {
            $messages = parse_ini_string((string) file_get_contents($file), false);
            if (false !== $messages && [] !== $messages) {
                $found[basename($file, '.ini')] = array_map('strval', $messages);
            }
        }

        return $found;
    }
}
