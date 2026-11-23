<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticPatchesBundle\EventListener\ContactDetailStatusSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\SegmentCampaignDetailStatusSubscriber;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticPatchesBundle\Service\LocalizedDateFormatter;
use MauticPlugin\MauticPatchesBundle\Service\SegmentPrefixStore;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('area:campaign')]
#[Group('risk:UI')]
#[Group('risk:FLUXO')]
class SegmentCampaignDetailStatusSubscriberTest extends TestCase
{
    /** @param array<string, mixed> $vars */
    private function html(array $vars, string $context = 'page.header.left'): string
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnArgument(0);
        $translator->method('getLocale')->willReturn('pt_BR');
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/s/contacts?search=segment:x');
        $event = new CustomContentEvent('some_view', $context, $vars);
        $params = $this->createMock(CoreParametersHelper::class);
        $params->method('get')->willReturnMap([['segment_rebuild_time_warning', null, 30], ['segment_build_time_warning', null, 30]]);
        (new SegmentCampaignDetailStatusSubscriber($translator, $params, $this->listModel(), new LocalizedDateFormatter(), $router, $this->twig(), $this->prefixes()))->injectViewCustomContent($event);

        return implode('', $event->getContent());
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 3).'/Resources/views', 'MauticPatches');

        return new Environment($loader);
    }

    private function prefixes(?string $prefix = null): SegmentPrefixStore
    {
        $store = $this->createMock(SegmentPrefixStore::class);
        $store->method('get')->willReturn($prefix);

        return $store;
    }

    private function listModel(): ListModel
    {
        $repository = $this->createMock(LeadListRepository::class);
        $repository->method('getLeadCount')->willReturn(6);
        $model = $this->createMock(ListModel::class);
        $model->method('getRepository')->willReturn($repository);

        return $model;
    }

    private function campaign(bool $published, bool $restart): Campaign
    {
        $campaign = new Campaign();
        $campaign->setIsPublished($published);
        $campaign->setAllowRestart($restart);

        return $campaign;
    }

    private function segment(bool $published): LeadList
    {
        $list = new LeadList();
        $list->setIsPublished($published);
        $list->setIsGlobal(false); // a new LeadList is global
        $list->setName('Segment');

        return $list;
    }

    public function testASegmentCarriesItsCountAndLastBuiltTextForTheBadgeAndTheLine(): void
    {
        $html = $this->html(['list' => $this->segment(true)]);

        $this->assertStringContainsString(' data-count="6"', $html);
        $this->assertStringContainsString('data-count-text="mautic.lead.list.viewleads_count"', $html);
        $this->assertStringContainsString('data-built-text="mautic.patches.segment.never_built"', $html);
        $this->assertStringContainsString('data-updating-text="mautic.patches.segment.updating"', $html);
    }

    public function testACampaignHasNoSegmentMarks(): void
    {
        $this->assertStringNotContainsString('data-segment-id', $this->html(['campaign' => $this->campaign(true, false)]));
    }

    public function testSubscribesToTheContent(): void
    {
        $this->assertArrayHasKey(CoreEvents::VIEW_INJECT_CUSTOM_CONTENT, SegmentCampaignDetailStatusSubscriber::getSubscribedEvents());
    }

    public function testAnActiveCampaignThatLetsContactsEnterAgainCarriesBothMarks(): void
    {
        $html = $this->html(['campaign' => $this->campaign(true, true)]);

        $this->assertStringContainsString('<span class="patches-detail-status" hidden data-status="published" data-restart="1"></span>', $html);
        $this->assertStringContainsString('mauticPatchesListStatusApi.installSegmentCampaignDetail(mQuery,Mautic,', $html);
    }

    public function testAnInactiveCampaignWithoutReentryHasNoReentryMark(): void
    {
        $html = $this->html(['campaign' => $this->campaign(false, false)]);

        $this->assertStringContainsString('data-status="unpublished"', $html);
        $this->assertStringNotContainsString('data-restart', $html);
    }

    public function testASegmentCarriesItsStatusAndNeverTheReentryMark(): void
    {
        $this->assertStringContainsString('data-status="published" data-segment-id=', $this->html(['list' => $this->segment(true)]));
        $this->assertStringContainsString('data-status="unpublished" data-segment-id=', $this->html(['list' => $this->segment(false)]));
        $this->assertStringNotContainsString('data-restart', $this->html(['list' => $this->segment(true)]));
    }

    public function testOnlyThePageHeaderLeftOfAPageWithASegmentOrCampaignGetsTheContent(): void
    {
        $this->assertSame('', $this->html(['campaign' => $this->campaign(true, true)], 'campaign.name'));
        $this->assertSame('', $this->html([]));
        $this->assertSame('', $this->html(['campaign' => new \stdClass()]));
        $this->assertSame('', $this->html(['list' => 'not an entity']));
    }

    public function testTheMarkerComesBeforeTheScriptBecauseTheScriptDrawsAtOnce(): void
    {
        $html = $this->html(['campaign' => $this->campaign(true, false)]);

        $this->assertLessThan(strpos($html, '<script'), strpos($html, 'patches-detail-status'));
    }

    public function testTheCampaignOfThePageWinsOverAListVariable(): void
    {
        $html = $this->html(['campaign' => $this->campaign(false, true), 'list' => $this->segment(true)]);

        $this->assertStringContainsString('data-status="unpublished" data-restart="1"', $html);
    }

    public function testTheDetailScriptLoadsAfterTheListScript(): void
    {
        $html = $this->html(['campaign' => $this->campaign(true, false)]);

        $this->assertLessThan(strpos($html, 'segment-campaign-detail-badges.js'), strpos($html, 'list-status-toggle.js'));
    }

    public function testTheLookIsTheOneOfTheContactPageUnderTheClassOfTheDetailsBox(): void
    {
        $style = SegmentCampaignDetailStatusSubscriber::style();

        $this->assertStringNotContainsString('patches-contact-badges', $style);
        $this->assertStringStartsWith(
            str_replace('.patches-contact-badges', '.patches-details-badges', ContactDetailStatusSubscriber::STYLE),
            $style
        );
        $this->assertStringContainsString('.patches-details-badges .patches-extra-badge', $style);
        $this->assertStringContainsString('height:40px', $style);
    }

    public function testASegmentWithNoFlagsCarriesNone(): void
    {
        $this->assertStringNotContainsString('data-flags', $this->html(['list' => $this->segment(true)]));
    }

    public function testAGlobalSegmentWithFiltersCarriesBothFlags(): void
    {
        $list = $this->segment(true);
        $list->setIsGlobal(true);
        $list->setFilters([['glue' => 'and', 'field' => 'email']]);

        $this->assertStringContainsString('data-status="published" data-flags="global,filters" data-segment-id=', $this->html(['list' => $list]));
    }

    public function testTheWarningsCarryTheirNumbersWhenAtTheThreshold(): void
    {
        $list = $this->segment(true);
        $list->setLastBuiltDate(new \DateTime('-72 hours'));
        $list->setLastBuiltTime(41.4);
        $html = $this->html(['list' => $list]);

        $this->assertStringContainsString(' data-flags="rebuild,slow" data-segment-id=', $html);
        $this->assertStringContainsString(' data-rebuild-hours="72" data-build-seconds="41"', $html);

        $list->setLastBuiltDate(new \DateTime('-30 hours'));
        $list->setLastBuiltTime(30.0);
        $this->assertStringContainsString('data-flags="rebuild,slow"', $this->html(['list' => $list]));
    }

    public function testTheWarningsStayOffBelowTheThresholdOrWithoutABuild(): void
    {
        $list = $this->segment(true);
        $list->setLastBuiltDate(new \DateTime('-29 hours'));
        $list->setLastBuiltTime(29.9);
        $this->assertStringNotContainsString('data-flags', $this->html(['list' => $list]));

        $this->assertStringNotContainsString('data-flags', $this->html(['list' => $this->segment(true)]));
    }

    public function testACampaignNeverCarriesSegmentFlags(): void
    {
        $this->assertStringNotContainsString('data-flags', $this->html(['campaign' => $this->campaign(true, true)]));
    }

    public function testTheNumbersAreRoundedToTheNearestUnit(): void
    {
        $list = $this->segment(true);
        $list->setLastBuiltDate(new \DateTime('-257760 seconds')); // 71.6 h
        $list->setLastBuiltTime(41.6);
        $this->assertStringContainsString('data-rebuild-hours="72" data-build-seconds="42"', $this->html(['list' => $list]));

        $list->setLastBuiltDate(new \DateTime('-260640 seconds')); // 72.4 h
        $this->assertStringContainsString('data-rebuild-hours="72" ', $this->html(['list' => $list]));
    }

    public function testJustUnderTheRebuildThresholdIsNotFlagged(): void
    {
        $list = $this->segment(true);
        $list->setLastBuiltDate(new \DateTime('-107990 seconds')); // 29.997 h

        $this->assertStringNotContainsString('data-flags', $this->html(['list' => $list]));
    }

    private function modalHead(LeadList $list): string
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnArgument(0);
        $translator->method('getLocale')->willReturn('pt_BR');
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/s/contacts?search=segment:x');
        $params = $this->createMock(CoreParametersHelper::class);
        $params->method('get')->willReturnMap([['segment_rebuild_time_warning', null, 30], ['segment_build_time_warning', null, 30]]);

        return (new SegmentCampaignDetailStatusSubscriber($translator, $params, $this->listModel(), new LocalizedDateFormatter(), $router, $this->twig(), $this->prefixes()))->modalHeadHtml($list);
    }

    public function testTheModalHeadCarriesTheSegmentNameEscapedAndTheSameMarkAsThePage(): void
    {
        $list = $this->segment(true);
        $list->setName('Clientes <b>"VIP"</b> & co');
        $html = $this->modalHead($list);

        $this->assertStringContainsString('class="patches-segment-header', $html);
        $this->assertStringContainsString('>Clientes &lt;b&gt;&quot;VIP&quot;&lt;/b&gt; &amp; co</h1>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('data-status="published"', $html);
        $this->assertStringContainsString(' data-count="6"', $html);
        $this->assertStringContainsString('data-built-text="mautic.patches.segment.never_built"', $html);
    }

    public function testTheModalHeadOfAnInactiveSegmentIsUnpublishedAndItsMarkerComesBeforeTheModalScript(): void
    {
        $html = $this->modalHead($this->segment(false));

        $this->assertStringContainsString('data-status="unpublished"', $html);
        $this->assertStringContainsString('installSegmentModalHead', $html);
        $this->assertLessThan(strpos($html, 'installSegmentModalHead'), strpos($html, 'patches-detail-status'));
    }

    public function testTheHeaderTitleIsThePrefixAndTheNameAndTheSamePartIsOnThePage(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('trans')->willReturnArgument(0);
        $translator->method('getLocale')->willReturn('pt_BR');
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/s/contacts');
        $params = $this->createMock(CoreParametersHelper::class);
        $params->method('get')->willReturnMap([['segment_rebuild_time_warning', null, 30], ['segment_build_time_warning', null, 30]]);
        $sub    = new SegmentCampaignDetailStatusSubscriber($translator, $params, $this->listModel(), new LocalizedDateFormatter(), $router, $this->twig(), $this->prefixes('ABC'));
        $event  = new CustomContentEvent('some_view', 'page.header.left', ['list' => $this->segment(true)]);
        $sub->injectViewCustomContent($event);
        $page = implode('', $event->getContent());

        $this->assertStringContainsString('>ABC - Segment</h1>', $page);
        $this->assertStringContainsString('class="patches-segment-updated', $page);
        $this->assertStringContainsString('>ABC - Segment</h1>', $sub->modalHeadHtml($this->segment(true)));
    }
}
