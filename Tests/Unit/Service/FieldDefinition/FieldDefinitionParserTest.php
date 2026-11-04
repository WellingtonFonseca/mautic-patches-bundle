<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\FieldDefinition;

use MauticPlugin\MauticPatchesBundle\Exception\FieldDefinitionImportException;
use MauticPlugin\MauticPatchesBundle\Service\FieldDefinition\FieldDefinitionParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldDefinitionParserTest extends TestCase
{
    private function parser(): FieldDefinitionParser
    {
        $translator = $this->createMock(TranslatorInterface::class);
        // the key and the parameters are the message: a test reads WHICH problem was found
        $translator->method('trans')->willReturnCallback(
            function (string $id, array $p = []): string {
                $prefix = 'mautic.patches.field_definition.error.';
                $this->assertStringStartsWith($prefix, $id);

                return substr($id, strlen($prefix)).($p ? ' '.implode(',', $p) : '');
            }
        );

        return new FieldDefinitionParser($translator);
    }

    /**
     * @param array<string, mixed> ...$fields
     */
    private static function json(array ...$fields): string
    {
        return (string) json_encode(['format' => 'custom-field-definition', 'version' => 1, 'fields' => $fields]);
    }

    private static function field(array $over = []): array
    {
        return array_merge(['object' => 'lead', 'alias' => 'abc', 'label' => 'Abc', 'type' => 'text'], $over);
    }

    /**
     * @return string[]
     */
    private function errorsOf(string $json): array
    {
        try {
            $this->parser()->parse($json);
        } catch (FieldDefinitionImportException $e) {
            return $e->getErrors();
        }
        $this->fail('the file was accepted');
    }

    #[DataProvider('badFiles')]
    public function testBadFile(string $json, string $error): void
    {
        $this->assertSame([$error], $this->errorsOf($json));
    }

    public static function badFiles(): array
    {
        return [
            'not json'      => ['nope', 'json'],
            'json scalar'   => ['5', 'json'],
            'other format'  => [(string) json_encode(['format' => 'x', 'version' => 1, 'fields' => [[]]]), 'format'],
            'other version' => [(string) json_encode(['format' => 'custom-field-definition', 'version' => 2, 'fields' => [[]]]), 'version'],
            'no fields'     => [self::json(), 'no_fields'],
            'fields scalar' => [(string) json_encode(['format' => 'custom-field-definition', 'version' => 1, 'fields' => 3]), 'no_fields'],
            'field scalar'  => [(string) json_encode(['format' => 'custom-field-definition', 'version' => 1, 'fields' => [7]]), 'not_object #1'],
            'missing object' => [self::json(['alias' => 'abc', 'label' => 'Abc', 'type' => 'text']), 'missing_key abc,object'],
            'alias tail'    => [self::json(self::field(['alias' => 'abc def'])), 'alias abc def'],
            'repeated'      => [self::json(self::field(), self::field()), 'repeated abc'],
            'missing label' => [self::json(['object' => 'lead', 'alias' => 'abc', 'type' => 'text']), 'missing_key abc,label'],
            'empty alias'   => [self::json(self::field(['alias' => ' '])), 'missing_key  ,alias'],
            'alias form'    => [self::json(self::field(['alias' => 'Bad Alias'])), 'alias Bad Alias'],
            'alias digit'   => [self::json(self::field(['alias' => '1abc'])), 'alias 1abc'],
            'object'        => [self::json(self::field(['object' => 'x'])), 'object abc,x'],
            'type'          => [self::json(self::field(['type' => 'nonsense'])), 'type abc,nonsense'],
            'group'         => [self::json(self::field(['group' => 'zz'])), 'group abc,"zz"'],
            'flag'          => [self::json(self::field(['isPublished' => 'yes'])), 'flag abc,isPublished'],
            'properties'    => [self::json(self::field(['properties' => 'x'])), 'properties abc'],
        ];
    }

    public function testEveryProblemIsCollected(): void
    {
        $errors = $this->errorsOf(self::json(self::field(['type' => 'nonsense']), self::field(['alias' => 'second', 'object' => 'x'])));

        $this->assertSame(['type abc,nonsense', 'object second,x'], $errors);
    }

    public function testDefaultsOfAMinimalField(): void
    {
        $fields = $this->parser()->parse(self::json(self::field(), self::field(['alias' => 'two', 'order' => 9])));

        $this->assertSame(
            ['object' => 'lead', 'alias' => 'abc', 'label' => 'Abc', 'type' => 'text', 'group' => 'core', 'order' => 1, 'charLengthLimit' => null,
                'defaultValue' => null, 'properties' => [], 'isPublished' => true, 'isRequired' => false, 'isListable' => true, 'isVisible' => true,
                'isShortVisible' => false, 'isPubliclyUpdatable' => false, 'isUniqueIdentifier' => false, 'isIndex' => false],
            $fields[0]
        );
        $this->assertSame(9, $fields[1]['order']);
    }

    public function testValuesOfAFullFieldAreKept(): void
    {
        $fields = $this->parser()->parse(self::json(self::field([
            'group' => 'social', 'charLengthLimit' => 20, 'defaultValue' => 5, 'properties' => ['list' => [['label' => 'A', 'value' => 'a']]],
            'isPublished' => false, 'isRequired' => true, 'isListable' => false, 'isVisible' => false, 'isShortVisible' => true,
            'isPubliclyUpdatable' => true, 'isUniqueIdentifier' => true, 'isIndex' => true,
        ])));

        $this->assertSame(
            ['object' => 'lead', 'alias' => 'abc', 'label' => 'Abc', 'type' => 'text', 'group' => 'social', 'order' => 1, 'charLengthLimit' => 20,
                'defaultValue' => '5', 'properties' => ['list' => [['label' => 'A', 'value' => 'a']]], 'isPublished' => false, 'isRequired' => true,
                'isListable' => false, 'isVisible' => false, 'isShortVisible' => true, 'isPubliclyUpdatable' => true, 'isUniqueIdentifier' => true, 'isIndex' => true],
            $fields[0]
        );
    }

    public function testEveryMissingKeyOfAFieldIsListed(): void
    {
        $this->assertSame(['missing_key abc,label', 'missing_key abc,type'], $this->errorsOf(self::json(['object' => 'lead', 'alias' => 'abc'])));
    }

    public function testEveryProblemOfOneFieldIsListed(): void
    {
        $errors = $this->errorsOf(self::json(self::field(['alias' => 'A b', 'object' => 'x', 'type' => 'nonsense'])));

        $this->assertSame(['alias A b', 'object A b,x', 'type A b,nonsense'], $errors);
    }

    public function testAFieldWithoutATextAliasIsNamedByItsPosition(): void
    {
        $this->assertSame(['missing_key #2,alias'], $this->errorsOf(self::json(self::field(), ['object' => 'lead', 'alias' => 5, 'label' => 'x', 'type' => 'text'])));
    }

    public function testARepeatedFieldDoesNotHideTheProblemsOfTheNext(): void
    {
        $errors = $this->errorsOf(self::json(self::field(), self::field(), self::field(['alias' => 'two', 'type' => 'nonsense'])));

        $this->assertSame(['repeated abc', 'type two,nonsense'], $errors);
    }
}
