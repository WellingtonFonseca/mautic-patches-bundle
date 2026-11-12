<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service;

use MauticPlugin\MauticPatchesBundle\EventListener\CampaignViewOrderSubscriber;
use MauticPlugin\MauticPatchesBundle\EventListener\EmailViewOrderSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\StatsBlockScript;
use PHPUnit\Framework\TestCase;

class StatsBlockScriptTest extends TestCase
{
    public function testTheScriptIsBuiltFromTheFlagTheAnchorAndTheVariableName(): void
    {
        $expected = '(function(){if(window.myFlag){return;}window.myFlag=true;function reorder(){'
            .'var $box=mQuery("#anchor").closest(".tab-content");var $menu=mQuery(".stats-menu");var $stats=mQuery(".stats-menu__content");'
            .'if(!$box.length||!$menu.length||!$stats.length){return;}'
            .'if($menu[0].compareDocumentPosition($box[0])&Node.DOCUMENT_POSITION_PRECEDING){return;}'
            .'$box.after($menu,$stats);window.dispatchEvent(new Event("resize"));}'
            .'var original=Mautic.onPageLoad;Mautic.onPageLoad=function(){var result=original.apply(this,arguments);reorder();return result;};'
            .'mQuery(reorder);})();';

        $this->assertSame($expected, StatsBlockScript::after('myFlag', '#anchor', 'box'));
    }

    public function testTheCampaignAndTheEmailPageUseItWithTheirOwnAnchor(): void
    {
        $this->assertSame(StatsBlockScript::after('mauticPatchesCampaignViewOrder', '#preview-container', 'preview'), CampaignViewOrderSubscriber::script());
        $this->assertSame(StatsBlockScript::after('mauticPatchesEmailViewOrder', '#clicks-container', 'tabs'), EmailViewOrderSubscriber::script());
    }
}
