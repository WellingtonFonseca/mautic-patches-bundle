<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Exception;

/**
 * The encrypted package of a roles / users clone cannot be opened: wrong key, damaged text or an unknown format.
 */
class ClonePackageException extends \RuntimeException
{
}
