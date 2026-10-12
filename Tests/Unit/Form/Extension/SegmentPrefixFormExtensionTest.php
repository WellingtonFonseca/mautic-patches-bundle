<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Extension;

use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\Form\Extension\SegmentPrefixFormExtension;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentPrefixFormExtensionTest extends TestCase
{
    /**
     * A form with a name field and the extension's listener, as ListType would have it.
     *
     * @return string[] the errors of the name field
     */
    private function errorsFor(?string $name, array $registered = ['Compras', 'Vendas']): array
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $params = []) => $key.($params ? ' '.json_encode($params) : ''));
        $prefixes = $this->createMock(SegmentPrefixes::class);
        $prefixes->method('all')->willReturn($registered);
        $prefixes->method('has')->willReturnCallback(static fn (string $p) => in_array($p, $registered, true));

        $builder = Forms::createFormFactory()->createBuilder(FormType::class);
        $builder->add('name', TextType::class);
        (new SegmentPrefixFormExtension($translator, $prefixes))->buildForm($builder, []);

        $form = $builder->getForm();
        $form->submit(['name' => $name]);

        return array_map(static fn ($e) => $e->getMessage(), iterator_to_array($form->get('name')->getErrors()));
    }

    public function testExtendsTheSegmentForm(): void
    {
        $this->assertSame([ListType::class], [...SegmentPrefixFormExtension::getExtendedTypes()]);
    }

    public function testNameWithPrefixHasNoError(): void
    {
        $this->assertSame([], $this->errorsFor('Compras | Onboarding'));
    }

    public function testNameWithoutPrefixIsRefused(): void
    {
        $this->assertSame(['mautic.patches.segment.prefix.required'], $this->errorsFor('Onboarding'));
    }

    public function testPrefixNotRegisteredIsRefusedWithTheList(): void
    {
        $this->assertSame(
            ['mautic.patches.segment.prefix.not_registered {"%list%":"Compras, Vendas"}'],
            $this->errorsFor('Compra | Onboarding')
        );
    }

    public function testPrefixIsCaseSensitive(): void
    {
        $this->assertCount(1, $this->errorsFor('compras | Onboarding'));
    }

    public function testNoPrefixRegisteredSaysSo(): void
    {
        $this->assertSame(['mautic.patches.segment.prefix.none_registered'], $this->errorsFor('Compras | Onboarding', []));
    }

    public function testBlankNameIsLeftToCore(): void
    {
        $this->assertSame([], $this->errorsFor(''));
        $this->assertSame([], $this->errorsFor(null));
    }
}
