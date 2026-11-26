<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Extension;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\DTO\SegmentDistinctConfig;
use MauticPlugin\MauticPatchesBundle\Form\Extension\SegmentDistinctFormExtension;
use MauticPlugin\MauticPatchesBundle\Service\IndexedLeadFields;
use MauticPlugin\MauticPatchesBundle\Service\SegmentDistinctStore;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('area:segment')]
#[Group('risk:DADOS')]
#[Group('risk:FLUXO')]
class SegmentDistinctFormExtensionTest extends TestCase
{
    /** @var array{0: LeadList, 1: SegmentDistinctConfig}|null */
    private ?array $remembered = null;

    /**
     * @param array<string, string> $indexed alias => label
     */
    private function form(array $indexed, ?SegmentDistinctConfig $stored = null): FormInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $k): string => $k);

        $fields = $this->createMock(IndexedLeadFields::class);
        $fields->method('all')->willReturn($indexed);

        $store = $this->createMock(SegmentDistinctStore::class);
        $store->method('get')->willReturn($stored);
        $store->method('remember')->willReturnCallback(function (LeadList $s, SegmentDistinctConfig $c): void {
            $this->remembered = [$s, $c];
        });

        $segment = new LeadList();
        $factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $builder = $factory->createBuilder(FormType::class, $segment, ['data_class' => LeadList::class]);
        (new SegmentDistinctFormExtension($translator, $fields, $store))->buildForm($builder, ['data' => $segment]);

        return $builder->getForm();
    }

    public function testExtendsTheSegmentForm(): void
    {
        $this->assertSame([ListType::class], [...SegmentDistinctFormExtension::getExtendedTypes()]);
    }

    public function testTwoFieldsSharingALabelBothAppearAndStayDistinct(): void
    {
        $form = $this->form(['email_work' => 'Email', 'email_home' => 'Email']);

        $choices = $form->get('distinct_fields')->getConfig()->getOption('choices');
        $this->assertCount(2, $choices);
        $this->assertSame(['email_work', 'email_home'], array_values($choices));
        $this->assertSame(['Email (email_work)', 'Email (email_home)'], array_keys($choices));
    }

    public function testFieldThatLostItsIndexStaysChosenAndMarked(): void
    {
        $form = $this->form(['city' => 'City'], new SegmentDistinctConfig(true, false, ['old_field']));

        $choices = $form->get('distinct_fields')->getConfig()->getOption('choices');
        $this->assertContains('old_field', $choices);
        $this->assertContains('city', $choices);
    }

    public function testEnabledWithoutFieldsIsRejected(): void
    {
        $form = $this->form(['city' => 'City']);
        $form->submit(['distinct_enabled' => '1', 'distinct_remove_empty' => '0', 'distinct_fields' => []]);

        $this->assertFalse($form->isValid());
        $this->assertNull($this->remembered);
    }

    public function testValidSubmitRemembersSortedUniqueFields(): void
    {
        $form = $this->form(['city' => 'City', 'email' => 'Email']);
        $form->submit(['distinct_enabled' => '1', 'distinct_remove_empty' => '1', 'distinct_fields' => ['email', 'city', 'email']]);

        $this->assertNotNull($this->remembered);
        $config = $this->remembered[1];
        $this->assertTrue($config->enabled);
        $this->assertTrue($config->removeEmpty);
        $this->assertSame(['city', 'email'], $config->fields);
    }
}
