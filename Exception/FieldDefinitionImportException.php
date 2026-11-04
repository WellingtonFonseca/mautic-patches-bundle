<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Exception;

/**
 * The file of a field definition import has problems. Every problem is collected, so the person fixes the file once.
 */
class FieldDefinitionImportException extends \RuntimeException
{
    /**
     * @param string[] $errors
     */
    public function __construct(private array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
