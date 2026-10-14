<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Form\Extension;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Form\Type\CampaignType;
use MauticPlugin\MauticPatchesBundle\Form\Extension\CampaignPrefixFormExtension;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixes;
use MauticPlugin\MauticPatchesBundle\Service\CampaignPrefixStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class CampaignPrefixFormExtensionTest extends TestCase
{
    /** @var array{0: Campaign, 1: string}|null what the store was asked to remember */
    private ?array $remembered = null;

    /**
     * A form with a name field and the extension's field, as CampaignType would have it, around $campaign.
     *
     * @param string[]    $registered
     * @param string|null $stored     the prefix the campaign already has
     */
    private function form(Campaign $campaign, array $registered = ['Compras', 'Vendas'], ?string $stored = null): FormInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $params = []) => $key.($params ? ' '.json_encode($params) : ''));

        $prefixes = $this->createMock(CampaignPrefixes::class);
        $prefixes->method('all')->willReturn($registered);

        $store = $this->createMock(CampaignPrefixStore::class);
        $store->method('get')->willReturn($stored);
        $store->method('remember')->willReturnCallback(function (Campaign $s, string $p): void {
            $this->remembered = [$s, $p];
        });

        $factory = Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory();
        $builder = $factory->createBuilder(FormType::class, $campaign, ['data_class' => Campaign::class]);
        $builder->add('name', TextType::class);
        (new CampaignPrefixFormExtension($translator, $prefixes, $store))->buildForm($builder, ['data' => $campaign]);

        return $builder->getForm();
    }

    /**
     * @return string[]
     */
    private function errors(FormInterface $form): array
    {
        return array_map(static fn ($e) => $e->getMessage(), iterator_to_array($form->get('prefix')->getErrors(true)));
    }

    public function testExtendsTheCampaignForm(): void
    {
        $this->assertSame([CampaignType::class], [...CampaignPrefixFormExtension::getExtendedTypes()]);
    }

    public function testARegisteredPrefixIsAcceptedAndRemembered(): void
    {
        $campaign = new Campaign();
        $form    = $this->form($campaign);
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Vendas']);

        $this->assertSame([], $this->errors($form));
        $this->assertSame([$campaign, 'Vendas'], $this->remembered);
    }

    public function testNoPrefixIsRefused(): void
    {
        $form = $this->form(new Campaign());
        $form->submit(['name' => 'Onboarding', 'prefix' => '']);

        $this->assertSame(['mautic.patches.campaign.prefix.required'], $this->errors($form));
        $this->assertNull($this->remembered);
    }

    public function testAPrefixNotRegisteredIsRefusedWithTheList(): void
    {
        $form = $this->form(new Campaign());
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Entregas']);

        $this->assertSame(['mautic.patches.campaign.prefix.not_registered {"%list%":"Compras, Vendas"}'], $this->errors($form));
        $this->assertNull($this->remembered);
    }

    public function testPrefixIsCaseSensitive(): void
    {
        $form = $this->form(new Campaign());
        $form->submit(['name' => 'Onboarding', 'prefix' => 'compras']);

        $this->assertCount(1, $this->errors($form));
    }

    public function testNoPrefixRegisteredSaysSo(): void
    {
        $form = $this->form(new Campaign(), []);
        $form->submit(['name' => 'Onboarding', 'prefix' => '']);

        $this->assertSame(['mautic.patches.campaign.prefix.none_registered'], $this->errors($form));
    }

    public function testTheFormStartsWithThePrefixTheCampaignHas(): void
    {
        $form = $this->form(new Campaign(), ['Compras'], 'Compras');

        $this->assertSame('Compras', $form->get('prefix')->getData());
    }

    public function testAPrefixNoLongerRegisteredStaysValidForTheCampaignThatHasIt(): void
    {
        $campaign = new Campaign();
        $form    = $this->form($campaign, ['Compras'], 'Entregas');
        $form->submit(['name' => 'Onboarding', 'prefix' => 'Entregas']);

        $this->assertSame([], $this->errors($form));
        $this->assertSame([$campaign, 'Entregas'], $this->remembered);
    }

    public function testAPatchWithoutThePrefixKeepsWhatIsStored(): void
    {
        $form = $this->form(new Campaign(), ['Compras'], 'Compras');
        $form->submit(['name' => 'Outro nome'], false);

        $this->assertSame([], $this->errors($form));
        $this->assertNull($this->remembered);
    }
}
