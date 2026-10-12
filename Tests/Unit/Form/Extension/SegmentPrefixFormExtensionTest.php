<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Extension;

use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\Form\Extension\SegmentPrefixFormExtension;
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
    private function errorsFor(?string $name): array
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $builder = Forms::createFormFactory()->createBuilder(FormType::class);
        $builder->add('name', TextType::class);
        (new SegmentPrefixFormExtension($translator))->buildForm($builder, []);

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
        $this->assertSame([], $this->errorsFor('PUCPR_POS | Onboarding'));
    }

    public function testNameWithoutPrefixIsRefused(): void
    {
        $this->assertSame(['mautic.patches.segment.prefix.required'], $this->errorsFor('Onboarding'));
    }

    public function testBlankNameIsLeftToCore(): void
    {
        $this->assertSame([], $this->errorsFor(''));
        $this->assertSame([], $this->errorsFor(null));
    }
}
