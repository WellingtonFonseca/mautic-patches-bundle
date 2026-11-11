<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\AuditLog;

use MauticPlugin\MauticPatchesBundle\Service\AuditLog\AuditTrail;
use PHPUnit\Framework\TestCase;

class AuditTrailTest extends TestCase
{
    public function testAnUpdateListsEachFieldWithItsOldAndNewValue(): void
    {
        $date = new \DateTime('2026-11-11 10:00');

        $rows = AuditTrail::rows([['dateAdded' => $date, 'userName' => 'Ana', 'action' => 'update', 'details' => ['subject' => ['Oi', 'Olá'], 'name' => ['A', 'B']]]]);

        $this->assertSame($date, $rows[0]['date']);
        $this->assertSame('Ana', $rows[0]['user']);
        $this->assertSame('update', $rows[0]['action']);
        $this->assertSame([['field' => 'subject', 'old' => 'Oi', 'new' => 'Olá'], ['field' => 'name', 'old' => 'A', 'new' => 'B']], $rows[0]['changes']);
    }

    public function testACreateHasNoOldValue(): void
    {
        $rows = AuditTrail::rows([['action' => 'create', 'details' => ['name' => [null, 'X']]]]);

        $this->assertSame([['field' => 'name', 'old' => '', 'new' => 'X']], $rows[0]['changes']);
    }

    public function testADeleteKeepsOnlyTheNameAsTheNewValue(): void
    {
        $rows = AuditTrail::rows([['action' => 'delete', 'details' => ['name' => 'Gone']]]);

        $this->assertSame([['field' => 'name', 'old' => '', 'new' => 'Gone']], $rows[0]['changes']);
    }

    public function testAnArrayValueIsShownAsJson(): void
    {
        $rows = AuditTrail::rows([['action' => 'update', 'details' => ['lists' => [[1], [1, 2]]]]]);

        $this->assertSame([['field' => 'lists', 'old' => '[1]', 'new' => '[1,2]']], $rows[0]['changes']);
    }

    public function testALongValueIsCutWithAnEllipsis(): void
    {
        $rows = AuditTrail::rows([['action' => 'update', 'details' => ['customHtml' => ['', str_repeat('é', 400)]]]]);

        $this->assertSame(301, mb_strlen($rows[0]['changes'][0]['new']));
        $this->assertStringEndsWith('…', $rows[0]['changes'][0]['new']);
    }

    public function testALineWithoutDetailsHasNoChanges(): void
    {
        $this->assertSame([], AuditTrail::rows([['action' => 'update', 'details' => null]])[0]['changes']);
        $this->assertSame([], AuditTrail::rows([])); 
    }

    public function testAValueOfExactlyTheLimitIsNotCut(): void
    {
        $rows = AuditTrail::rows([['action' => 'update', 'details' => ['x' => ['', str_repeat('a', 300)]]]]);

        $this->assertSame(300, mb_strlen($rows[0]['changes'][0]['new']));
    }
}
