<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\MauticPatchesBundle\Form\Type\FeatureSwitchType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[Group('area:plugin-settings')]
#[Group('risk:UI')]
class FeatureSwitchTypeTest extends TestCase
{
    private function resolve(array $options): array
    {
        $resolver = new OptionsResolver();
        (new FeatureSwitchType())->configureOptions($resolver);

        return $resolver->resolve($options);
    }

    public function testItIsCoresYesNoSwitchWithItsOwnBlockPrefix(): void
    {
        $type = new FeatureSwitchType();

        $this->assertSame(YesNoButtonGroupType::class, $type->getParent());
        $this->assertSame('patches_feature_switch', $type->getBlockPrefix());
    }

    public function testTheAlertDefaultsToNothing(): void
    {
        $this->assertSame('', $this->resolve([])['alert']);
    }

    public function testTheAlertMustBeATranslationKeyString(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->resolve(['alert' => ['not', 'a', 'string']]);
    }

    public function testTheAlertReachesTheView(): void
    {
        $view = new FormView();

        (new FeatureSwitchType())->buildView($view, $this->createMock(FormInterface::class), ['alert' => 'mautic.patches.settings.keyset_rebuild.note']);

        $this->assertSame('mautic.patches.settings.keyset_rebuild.note', $view->vars['alert']);
    }
}
