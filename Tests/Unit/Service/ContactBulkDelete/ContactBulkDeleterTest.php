<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\ContactBulkDelete;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticPatchesBundle\Service\ContactBulkDelete\ContactBulkDeleter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContactBulkDeleterTest extends TestCase
{
    /** @var LeadModel&MockObject */
    private LeadModel $model;

    protected function setUp(): void
    {
        $this->model = $this->createMock(LeadModel::class);
    }

    private function contact(int $id, string $email): Lead
    {
        $lead = new Lead();
        $lead->setId($id);
        $lead->addUpdatedField('email', $email);

        return $lead;
    }

    public function testCountReturnsTheTotalOfTheSearch(): void
    {
        $this->model->expects(self::once())->method('getEntities')->with([
            'filter'         => ['string' => 'x'],
            'start'          => 0,
            'limit'          => 1,
            'orderBy'        => 'l.id',
            'orderByDir'     => 'ASC',
            'withTotalCount' => true,
        ])->willReturn(['results' => [7 => $this->contact(7, 'a@x.com')], 'count' => '650']);

        self::assertSame(650, (new ContactBulkDeleter($this->model))->count('x'));
    }

    public function testDeleteBatchDeletesTheFoundIdsAndReportsWhatRemains(): void
    {
        $this->model->method('getEntities')->willReturn(['results' => [3 => $this->contact(3, 'a'), 9 => $this->contact(9, 'b')], 'count' => 10]);
        $this->model->expects(self::once())->method('deleteEntities')->with([3, 9]);

        self::assertSame(['deleted' => 2, 'remaining' => 8], (new ContactBulkDeleter($this->model))->deleteBatch('x', 2));
    }

    public function testDeleteBatchWithNoMatchDeletesNothing(): void
    {
        $this->model->method('getEntities')->willReturn(['results' => [], 'count' => 0]);
        $this->model->expects(self::never())->method('deleteEntities');

        self::assertSame(['deleted' => 0, 'remaining' => 0], (new ContactBulkDeleter($this->model))->deleteBatch('x'));
    }
}
