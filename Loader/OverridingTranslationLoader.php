<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Loader;

use Mautic\CoreBundle\Loader\TranslationLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Core's translation loader reads the bundles' Translations first and the downloaded packs (translations/<locale>) after them, so a
 * plugin's own `Translations/<locale>/messages.ini` can never replace a text of the pack. This loader reads core's catalogue and then
 * puts on top of it the texts of Resources/translation-overrides/<locale>/messages.ini, for the texts the pack gets wrong:
 *  - pt_BR `mautic.core.close_back` names its placeholder %alvo% while core's templates fill %target%, so every "Back to <list>" button
 *    read "Voltar ao %alvo%".
 * Only the keys of that file are touched. Registered under core's service id in Config/services.php.
 */
class OverridingTranslationLoader extends TranslationLoader
{
    public const OVERRIDES_DIR = __DIR__.'/../Resources/translation-overrides';

    public function load($resource, $locale, $domain = 'messages')
    {
        $catalogue = parent::load($resource, $locale, $domain);

        $overrides = self::overrides($locale);
        if ([] !== $overrides) {
            $catalogue->add($overrides, 'messages');
        }

        return $catalogue;
    }

    /**
     * @return array<string, string>
     */
    public static function overrides(string $locale, string $dir = self::OVERRIDES_DIR): array
    {
        $file = $dir.'/'.$locale.'/messages.ini';
        if (!is_file($file)) {
            return [];
        }

        $messages = parse_ini_string((string) file_get_contents($file), false);

        return false === $messages ? [] : array_map('strval', $messages);
    }
}
