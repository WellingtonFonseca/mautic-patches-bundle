<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Extension;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Form\Type\ListType;
use MauticPlugin\MauticPatchesBundle\Form\Extension\SegmentPrefixFormExtension;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixes;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentPrefixFormExtensionTest extends TestCase
{
    /** @var array{0: LeadList, 1: string}|null what the store was asked to remember */
    private ?array $remembered = null;

    /**
     * A form with a name field and the extension's field, as ListType would have it, around $segment.
     *
     * @param string[]    $registered
     * @param string|null $stored     the prefix the segment already has
     */
    private function form(LeadList $segment, array $registered = ['Compras', 'Vendas'], ?string $stored = null): FormInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $params = []) => $key.($params ? ' '.json_encode($params) : ''));

        $prefixes = $this->createMock(SegmentPrefixes::class);
        $prefixes->method('all')->willReturn($registered);

        $store = $this->createMock(SegmentPrefixStore::class);
        $store->method('get')->willReturn($stored);
        $store->method('remember')->willReturnCallback(function (LeadList $s, string $p): void {
            $this->remembered = [$s, $p];
        });

        $factory = Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();
        $builder = $factory->createBuilder(FormType::class, $segment, ['data_class' => LeadList::class]);
        $builder->add('name', TextType::class);
        $builder->add('alias', TextType::class, ['required' => false]);
        (new SegmentPrefixFormExtension($translator, $prefixes, $store))->buildForm($builder, ['data' => $segment]);

        return $builder->getForm();
    }

    /**
     * @return string[]
     */
    private function errors(FormInterface $form): array
    {
        return array_map(static fn ($e) => $e->getMessage(), iterator_to_array($form->get('prefix')->getErrors(true)));
    }

    public function testExtendsTheSegmentForm(): void
    {
        $this->assertSame([ListType::class], [...SegmentPrefixFormExtension::getExtendedTypes()]);
    }

    public function testARegisteredPrefixIsAcceptedAndRemembered(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment);
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Vendas']);

        $this->assertSame([], $this->errors($form));
        $this->assertSame([$segment, 'Vendas'], $this->remembered);
    }

    public function testNoPrefixIsRefused(): void
    {
        $form = $this->form(new LeadList());
        $form->submit(['name' => 'Onboarding', 'prefix' => '']);

        $this->assertSame(['mautic.patches.segment.prefix.required'], $this->errors($form));
        $this->assertNull($this->remembered);
    }

    public function testAPrefixNotRegisteredIsRefusedWithTheList(): void
    {
        $form = $this->form(new LeadList());
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Entregas']);

        $this->assertSame(['mautic.patches.segment.prefix.not_registered {"%list%":"Compras, Vendas"}'], $this->errors($form));
        $this->assertNull($this->remembered);
    }

    public function testPrefixIsCaseSensitive(): void
    {
        $form = $this->form(new LeadList());
        $form->submit(['name' => 'Onboarding', 'prefix' => 'compras']);

        $this->assertCount(1, $this->errors($form));
    }

    public function testNoPrefixRegisteredSaysSo(): void
    {
        $form = $this->form(new LeadList(), []);
        $form->submit(['name' => 'Onboarding', 'prefix' => '']);

        $this->assertSame(['mautic.patches.segment.prefix.none_registered'], $this->errors($form));
    }

    public function testTheFormStartsWithThePrefixTheSegmentHas(): void
    {
        $form = $this->form(new LeadList(), ['Compras'], 'Compras');

        $this->assertSame('Compras', $form->get('prefix')->getData());
    }

    public function testAPrefixNoLongerRegisteredStaysValidForTheSegmentThatHasIt(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment, ['Compras'], 'Entregas');
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Entregas']);

        $this->assertSame([], $this->errors($form));
        $this->assertSame([$segment, 'Entregas'], $this->remembered);
    }

    public function testNoAliasTypedGetsPrefixAndNameInLettersAndNumbersOnly(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment);
        $form->submit(['name' => 'nome - do meu segmento', 'alias' => '', 'prefix' => 'Compras']);

        $this->assertSame('comprasnomedomeusegmento', $segment->getAlias());
    }

    public function testATypedAliasIsKept(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment);
        $form->submit(['name' => 'Onboarding', 'alias' => 'meu-alias_1', 'prefix' => 'Compras']);

        $this->assertSame('meu-alias_1', $segment->getAlias());
    }

    public function testApiWithoutAliasFieldAlsoGetsTheGeneratedAlias(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment);
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Vendas'], false);

        $this->assertSame('vendasonboarding', $segment->getAlias());
    }

    public function testAPatchWithoutThePrefixKeepsWhatIsStored(): void
    {
        $form = $this->form(new LeadList(), ['Compras'], 'Compras');
        $form->submit(['name' => 'Outro nome'], false);

        $this->assertSame([], $this->errors($form));
        $this->assertNull($this->remembered);
    }

    public function testThePublicNameIsAlwaysPrefixAndName(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment);
        $form->submit(['name' => 'Onboarding', 'publicName' => 'qualquer coisa', 'prefix' => 'Compras']);

        $this->assertSame('Compras - Onboarding', $segment->getPublicName());
    }

    public function testNoPublicNameTypedAlsoGetsPrefixAndName(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment);
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Vendas'], false);

        $this->assertSame('Vendas - Onboarding', $segment->getPublicName());
    }

    public function testAPatchWithoutThePrefixUsesTheStoredOne(): void
    {
        $segment = new LeadList();
        $form    = $this->form($segment, ['Compras'], 'Compras');
        $form->submit(['name' => 'Outro nome'], false);

        $this->assertSame('Compras - Outro nome', $segment->getPublicName());
    }
}
