<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RelativeWait;

use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitFieldCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RelativeWaitFieldCatalogTest extends TestCase
{
    private function catalog(array $contactFields, array $objectFields): RelativeWaitFieldCatalog
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(static fn (string $sql): array => str_contains($sql, 'lead_fields') ? $contactFields : $objectFields);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Contato');

        return new RelativeWaitFieldCatalog($connection, $translator);
    }

    public function testTheContactAndEachObjectAreGroupsOfValuesTheResolverReads(): void
    {
        $choices = $this->catalog(
            [['alias' => 'attribution_date', 'label' => 'Attribution date']],
            [['object' => 'disciplina', 'id' => '33', 'label' => 'inicio'], ['object' => 'disciplina', 'id' => '34', 'label' => 'fim']]
        )->choices();

        $this->assertSame(['Contato' => ['Attribution date' => 'contact:attribution_date'], 'disciplina' => ['inicio' => 'custom_object:33', 'fim' => 'custom_object:34']], $choices);
    }

    public function testTwoFieldsWithTheSameLabelCanBothBeChosen(): void
    {
        $choices = $this->catalog(
            [['alias' => 'a_date', 'label' => 'Start'], ['alias' => 'b_date', 'label' => 'Start']],
            [['object' => 'disciplina', 'id' => '33', 'label' => 'Inicio'], ['object' => 'disciplina', 'id' => '40', 'label' => 'Inicio']]
        )->choices();

        $this->assertSame(['Start' => 'contact:a_date', 'Start (b_date)' => 'contact:b_date'], $choices['Contato']);
        $this->assertSame(['Inicio' => 'custom_object:33', 'Inicio (#40)' => 'custom_object:40'], $choices['disciplina']);
    }

    public function testWithoutTheCustomObjectsPluginOnlyTheContactFieldsAreOffered(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'custom_field')) {
                throw new \RuntimeException('no such table');
            }

            return [['alias' => 'attribution_date', 'label' => 'Attribution date']];
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Contato');

        $this->assertSame(['Contato' => ['Attribution date' => 'contact:attribution_date']], (new RelativeWaitFieldCatalog($connection, $translator))->choices());
    }
}
