<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Extension;

use Mautic\CampaignBundle\Form\Type\EventType;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Form\Extension\RelativeWaitNameExtension;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

#[Group('area:campaign-wait')]
#[Group('area:campaign')]
#[Group('risk:FLUXO')]
class RelativeWaitNameExtensionTest extends TestCase
{
    private function form(array $data = null): FormInterface
    {
        $formatter = $this->createMock(RelativeWaitNameFormatter::class);
        $formatter->method('fromProperties')->willReturnCallback(function (?array $properties): string {
            $amount = $properties['amount'] ?? 1;
            $unit   = $properties['unit'] ?? 'd';

            return "Esperar {$amount} {$unit}";
        });

        $data = $data ?? ['type' => RelativeWaitSubscriber::TYPE];

        $factory = Forms::createFormFactory();
        $builder = $factory->createBuilder(FormType::class, $data, ['data_class' => null]);
        (new RelativeWaitNameExtension($formatter))->buildForm($builder, ['data' => $data]);

        return $builder->getForm();
    }

    public function testExtendsTheEventForm(): void
    {
        $this->assertSame([EventType::class], [...RelativeWaitNameExtension::getExtendedTypes()]);
    }

    public function testAddsHiddenNameFieldForWaitType(): void
    {
        $form = $this->form(['type' => RelativeWaitSubscriber::TYPE]);

        // The name field should be added for wait type
        $this->assertTrue($form->has('name'), 'name field should be added for wait type');
    }

    public function testDoesNotAddNameFieldForNonWaitType(): void
    {
        $form = $this->form(['type' => 'email.send']);

        // The name field should not be added for other types
        $this->assertFalse($form->has('name'), 'name field should not be added for non-wait types');
    }

    public function testCalculatesNameFromPropertiesOnPreSubmit(): void
    {
        $form = $this->form(['type' => RelativeWaitSubscriber::TYPE]);

        $submittedData = [
            'type'       => RelativeWaitSubscriber::TYPE,
            'properties' => ['amount' => 2, 'unit' => 'd'],
        ];

        $form->submit($submittedData);

        // After PRE_SUBMIT event, the name should be calculated from properties
        $formData = $form->getData();
        $this->assertSame('Esperar 2 d', $formData['name']);
    }

    public function testNameFieldIsHiddenType(): void
    {
        $form = $this->form(['type' => RelativeWaitSubscriber::TYPE]);

        // The name field should be hidden
        $nameField = $form->get('name');
        $this->assertSame('Symfony\Component\Form\Extension\Core\Type\HiddenType', get_class($nameField->getConfig()->getType()->getInnerType()));
    }
}
