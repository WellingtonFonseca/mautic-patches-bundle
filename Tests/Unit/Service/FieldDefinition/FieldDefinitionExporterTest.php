<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\FieldDefinition;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionExporter;
use PHPUnit\Framework\TestCase;

class FieldDefinitionExporterTest extends TestCase
{
    private function exporter(LeadField ...$fields): FieldDefinitionExporter
    {
        $repository = $this->createMock(LeadFieldRepository::class);
        $repository->method('findBy')->with([], ['object' => 'ASC', 'order' => 'ASC', 'id' => 'ASC'])->willReturn($fields);
        $model = $this->createMock(FieldModel::class);
        $model->method('getRepository')->willReturn($repository);

        return new FieldDefinitionExporter($model);
    }

    private function field(): LeadField
    {
        $field = new LeadField();
        $field->setObject('company');
        $field->setAlias('abc');
        $field->setLabel('Ação');
        $field->setType('select');
        $field->setGroup('professional');
        $field->setOrder(4);
        $field->setIsPublished(false);
        $field->setIsFixed(true);
        $field->setIsRequired(true);
        $field->setIsListable(false);
        $field->setIsVisible(false);
        $field->setIsShortVisible(true);
        $field->setIsPubliclyUpdatable(true);
        $field->setIsUniqueIdentifier(true);
        $field->setIsIndex(true);
        $field->setCharLengthLimit(20);
        $field->setDefaultValue('x');
        $field->setProperties(['list' => [['label' => 'A', 'value' => 'a']]]);

        return $field;
    }

    public function testEveryFieldIsInTheFileWithItsStatusAndNoIdOrDate(): void
    {
        $data = $this->exporter($this->field())->toArray();

        $this->assertSame('custom-field-definition', $data['format']);
        $this->assertSame(1, $data['version']);
        $this->assertSame(
            ['object' => 'company', 'alias' => 'abc', 'label' => 'Ação', 'type' => 'select', 'group' => 'professional', 'order' => 4,
                'isPublished' => false, 'isFixed' => true, 'isRequired' => true, 'isListable' => false, 'isVisible' => false, 'isShortVisible' => true,
                'isPubliclyUpdatable' => true, 'isUniqueIdentifier' => true, 'isIndex' => true, 'charLengthLimit' => 20, 'defaultValue' => 'x',
                'properties' => ['list' => [['label' => 'A', 'value' => 'a']]]],
            $data['fields'][0]
        );
    }

    public function testTheJsonKeepsAccentsAndSlashesAndReadsBack(): void
    {
        $json = $this->exporter($this->field())->toJson();

        $this->assertStringContainsString('"label": "Ação"', $json);
        $this->assertSame($this->exporter($this->field())->toArray(), json_decode($json, true));
    }

    public function testTheFlagsAreBooleansEvenWhenTheDatabaseGivesNumbers(): void
    {
        $field = new LeadField();
        $field->setIsRequired(1);
        $field->setIsListable(0);
        $field->setIsVisible(1);
        $field->setIsShortVisible(0);
        $field->setIsPubliclyUpdatable(1);
        $field->setIsUniqueIdentifier(1);

        $data = $this->exporter($field)->toArray()['fields'][0];

        foreach (['isRequired' => true, 'isListable' => false, 'isVisible' => true, 'isShortVisible' => false, 'isPubliclyUpdatable' => true, 'isUniqueIdentifier' => true] as $key => $expected) {
            $this->assertSame($expected, $data[$key], $key);
        }
    }
}
