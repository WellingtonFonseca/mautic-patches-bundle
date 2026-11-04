<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\FieldDefinition;

use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Field\Exception\AbortColumnCreateException;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\MauticPatchesBundle\Exception\FieldDefinitionImportException;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionImporter;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldDefinitionImporterTest extends TestCase
{
    /** @var array<string, LeadField> the fields "of this instance", by alias */
    private array $existing = [];

    /** @var MockObject&FieldModel */
    private $fieldModel;

    /** @var MockObject&LeadFieldRepository */
    private $repository;

    private ConstraintViolationList $violations;

    /** @var array<string, LeadList[]> */
    private array $segments = [];

    protected function setUp(): void
    {
        $this->violations = new ConstraintViolationList();
        $this->repository = $this->createMock(LeadFieldRepository::class);
        $this->repository->method('findAll')->willReturnCallback(fn (): array => array_values($this->existing));
        $this->fieldModel = $this->createMock(FieldModel::class);
        $this->fieldModel->method('getRepository')->willReturn($this->repository);
        $this->fieldModel->method('getFieldSegments')->willReturnCallback(fn (LeadField $f): array => $this->segments[$f->getAlias()] ?? []);
    }

    private function importer(): FieldDefinitionImporter
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id, array $p = []): string => $id.($p ? ' '.implode(',', $p) : ''));
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn($this->violations);

        return new FieldDefinitionImporter($this->fieldModel, new FieldDefinitionParser($translator), $validator, $translator);
    }

    private function exists(string $alias, bool $published = true, string $type = 'text', bool $identifier = false, string $object = 'lead'): LeadField
    {
        $field = new LeadField();
        $field->setAlias($alias);
        $field->setObject($object);
        $field->setType($type);
        $field->setLabel('Old '.$alias);
        $field->setIsPublished($published);
        $field->setIsUniqueIdentifier($identifier);

        return $this->existing[$alias] = $field;
    }

    /**
     * @param array<string, mixed> ...$fields
     */
    private static function json(array ...$fields): string
    {
        return (string) json_encode(['format' => 'custom-field-definition', 'version' => 1, 'fields' => $fields]);
    }

    private static function field(string $alias, array $over = []): array
    {
        return array_merge(['object' => 'lead', 'alias' => $alias, 'label' => 'New '.$alias, 'type' => 'text'], $over);
    }

    public function testACheckSavesNothingAndNamesWhatItWouldDo(): void
    {
        $on  = $this->exists('on', false);
        $off = $this->exists('off', true);
        $this->exists('same', true);
        $this->fieldModel->expects($this->never())->method('saveEntity');
        $this->repository->expects($this->never())->method('saveEntities');

        $result = $this->importer()->import(
            self::json(self::field('brand_new'), self::field('on', ['isPublished' => true]), self::field('off', ['isPublished' => false]), self::field('same')),
            false
        );

        $this->assertSame(['lead: brand_new'], $result->getCreated());
        $this->assertSame(['lead: on'], $result->getActivated());
        $this->assertSame(['lead: off'], $result->getDeactivated());
        $this->assertSame(1, $result->getUnchanged());
        $this->assertTrue($result->hasChanges());
        $this->assertFalse($on->getIsPublished());
        $this->assertTrue($off->getIsPublished());
    }

    public function testTheApplyCreatesTheFieldWithTheValuesOfTheFile(): void
    {
        $saved = [];
        $this->fieldModel->expects($this->once())->method('saveEntity')->willReturnCallback(function (LeadField $f) use (&$saved): void { $saved[] = $f; });

        $this->importer()->import(self::json(self::field('brand_new', [
            'object' => 'company', 'group' => 'professional', 'order' => 7, 'isPublished' => false, 'isRequired' => true, 'isListable' => false,
            'isVisible' => false, 'isShortVisible' => true, 'isPubliclyUpdatable' => true, 'isUniqueIdentifier' => true, 'isIndex' => true,
            'charLengthLimit' => 30, 'defaultValue' => 'x', 'properties' => ['a' => 1], 'type' => 'select',
        ])));

        $field = $saved[0];
        $this->assertSame(['company', 'brand_new', 'New brand_new', 'select', 'professional', 7], [$field->getObject(), $field->getAlias(), $field->getLabel(), $field->getType(), $field->getGroup(), $field->getOrder()]);
        $this->assertSame([false, true, false, false, true, true, true, true, 30, 'x', ['a' => 1]], [
            $field->getIsPublished(), $field->getIsRequired(), $field->getIsListable(), $field->getIsVisible(), $field->getIsShortVisible(),
            $field->getIsPubliclyUpdatable(), $field->getIsUniqueIdentifier(), $field->isIsIndex(), $field->getCharLengthLimit(), $field->getDefaultValue(), $field->getProperties(),
        ]);
    }

    public function testTheApplySwitchesFieldsAndNeverTouchesTheirLabelOrType(): void
    {
        $on  = $this->exists('on', false, 'number');
        $off = $this->exists('off', true);
        $this->repository->expects($this->once())->method('saveEntities')->with($this->callback(fn (array $fields): bool => 2 === count($fields)));
        $this->fieldModel->expects($this->never())->method('saveEntity');

        $this->importer()->import(self::json(self::field('on', ['isPublished' => true, 'type' => 'text']), self::field('off', ['isPublished' => false])));

        $this->assertTrue($on->getIsPublished());
        $this->assertFalse($off->getIsPublished());
        $this->assertSame('Old on', $on->getLabel());
        $this->assertSame('number', $on->getType());
    }

    public function testAnExistingFieldWithAnotherTypeIsAWarningEvenWhenItStaysAsItIs(): void
    {
        $this->exists('kept', true, 'number');

        $result = $this->importer()->import(self::json(self::field('kept', ['type' => 'text'])), false);

        $this->assertSame(['lead: kept'], $result->getKeptType());
        $this->assertFalse($result->hasChanges());
        $this->assertSame(1, $result->getUnchanged());
    }

    public function testAUniqueIdentifierIsNotDeactivated(): void
    {
        $email = $this->exists('email', true, 'email', true);
        $this->repository->expects($this->never())->method('saveEntities');

        $result = $this->importer()->import(self::json(self::field('email', ['isPublished' => false])));

        $this->assertSame(['lead: email'], $result->getKeptIdentifier());
        $this->assertSame([], $result->getDeactivated());
        $this->assertTrue($email->getIsPublished());
    }

    public function testADeactivatedFieldNamesTheSegmentsThatUseIt(): void
    {
        $this->exists('used', true);
        $this->exists('free', true);
        $segment = new LeadList();
        $segment->setName('Seg A');
        $this->segments['used'] = [$segment];

        $result = $this->importer()->import(self::json(self::field('used', ['isPublished' => false]), self::field('free', ['isPublished' => false])), false);

        $this->assertSame(['lead: used' => ['Seg A']], $result->getDeactivatedInUse());
        $this->assertSame(['lead: used', 'lead: free'], $result->getDeactivated());
    }

    public function testAnAliasOfAnotherObjectIsAnErrorAndNothingIsSaved(): void
    {
        $this->exists('clash', true, 'text', false, 'company');
        $this->fieldModel->expects($this->never())->method('saveEntity');

        try {
            $this->importer()->import(self::json(self::field('clash'), self::field('fine')));
            $this->fail('no error');
        } catch (FieldDefinitionImportException $e) {
            $this->assertSame(['mautic.patches.field_definition.error.other_object clash,company'], $e->getErrors());
        }
    }

    public function testTheViolationsOfANewFieldStopEverythingBeforeTheFirstSave(): void
    {
        $this->exists('on', false);
        $this->violations->add(new ConstraintViolation('too long', 'too long', ['%n%' => '5'], null, 'label', null));
        $this->fieldModel->expects($this->never())->method('saveEntity');
        $this->repository->expects($this->never())->method('saveEntities');

        try {
            $this->importer()->import(self::json(self::field('on', ['isPublished' => true]), self::field('bad')));
            $this->fail('no error');
        } catch (FieldDefinitionImportException $e) {
            $this->assertSame(['lead: bad: too long 5'], $e->getErrors());
        }
    }

    public function testAFieldWhoseColumnIsMadeInTheBackgroundIsReported(): void
    {
        $this->fieldModel->method('saveEntity')->willReturnCallback(function (LeadField $f): void {
            if ('slow' === $f->getAlias()) {
                throw new AbortColumnCreateException('background');
            }
        });

        $result = $this->importer()->import(self::json(self::field('slow'), self::field('quick')));

        $this->assertSame(['lead: slow'], $result->getColumnsInBackground());
        $this->assertSame(['lead: slow', 'lead: quick'], $result->getCreated());
    }

    public function testAFileThatMatchesHasNoChanges(): void
    {
        $this->exists('same', true);

        $result = $this->importer()->import(self::json(self::field('same')));

        $this->assertFalse($result->hasChanges());
        $this->assertSame(1, $result->getUnchanged());
    }

    public function testEveryClashOfAnAliasIsListed(): void
    {
        $this->exists('one', true, 'text', false, 'company');
        $this->exists('two', true, 'text', false, 'company');

        try {
            $this->importer()->import(self::json(self::field('one'), self::field('two')));
            $this->fail('no error');
        } catch (FieldDefinitionImportException $e) {
            $this->assertSame(['mautic.patches.field_definition.error.other_object one,company', 'mautic.patches.field_definition.error.other_object two,company'], $e->getErrors());
        }
    }

    public function testEveryViolationOfEveryNewFieldIsListed(): void
    {
        $this->violations->add(new ConstraintViolation('one', 'one', [], null, 'label', null));
        $this->violations->add(new ConstraintViolation('two', 'two', [], null, 'alias', null));

        try {
            $this->importer()->import(self::json(self::field('bad')));
            $this->fail('no error');
        } catch (FieldDefinitionImportException $e) {
            $this->assertSame(['lead: bad: one', 'lead: bad: two'], $e->getErrors());
        }
    }

    public function testASeveralFieldsInTheSameStateAreAllReportedAndAFieldAfterAnEarlyExitIsStillRead(): void
    {
        $this->exists('kept', true, 'number');
        $this->exists('id', true, 'text', true);
        $this->exists('on', false);
        $this->exists('off', true);
        $this->exists('same', true);
        $first = new LeadList();
        $first->setName('S1');
        $second = new LeadList();
        $second->setName('S2');
        $this->segments['off'] = [$first, $second];

        $result = $this->importer()->import(self::json(
            self::field('kept', ['type' => 'text']),
            self::field('id', ['isPublished' => false]),
            self::field('same'),
            self::field('on', ['isPublished' => true]),
            self::field('off', ['isPublished' => false]),
            self::field('last_new')
        ), false);

        $this->assertSame(['lead: kept'], $result->getKeptType());
        $this->assertSame(['lead: id'], $result->getKeptIdentifier());
        $this->assertSame(['lead: on'], $result->getActivated());
        $this->assertSame(['lead: off'], $result->getDeactivated());
        $this->assertSame(['lead: off' => ['S1', 'S2']], $result->getDeactivatedInUse());
        $this->assertSame(['lead: last_new'], $result->getCreated());
        $this->assertSame(2, $result->getUnchanged());
    }

    public function testSeveralColumnsInTheBackgroundAreAllReported(): void
    {
        $this->fieldModel->method('saveEntity')->willThrowException(new AbortColumnCreateException('background'));

        $result = $this->importer()->import(self::json(self::field('one'), self::field('two')));

        $this->assertSame(['lead: one', 'lead: two'], $result->getColumnsInBackground());
    }

    public function testAnIndexedFieldThatIsNotAUniqueIdentifierStaysIndexed(): void
    {
        $saved = null;
        $this->fieldModel->method('saveEntity')->willReturnCallback(function (LeadField $f) use (&$saved): void { $saved = $f; });

        $this->importer()->import(self::json(self::field('indexed', ['isIndex' => true, 'isUniqueIdentifier' => false])));

        $this->assertTrue($saved->isIsIndex());
        $this->assertFalse($saved->getIsUniqueIdentifier());
    }
}
