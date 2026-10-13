<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Helper\InputHelper;
use MauticPlugin\MauticPatchesBundle\Service\SegmentFilterValues;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * GET /s/segment-filter-values?object=lead&field=inst_alias&q=abc&limit=20: the suggestions of the segment form's
 * including / excluding chips for a contact or company text field (see Service/SegmentFilterValues). Needs the right to
 * see contacts, since it shows values stored in them.
 */
class SegmentFilterValuesController extends CommonController
{
    public function valuesAction(Request $request, SegmentFilterValues $values): JsonResponse
    {
        if (!$this->security->isGranted(['lead:leads:viewown', 'lead:leads:viewother'], 'MATCH_ONE')) {
            return new JsonResponse(['values' => []], 403);
        }

        try {
            return new JsonResponse(['values' => $values->find(
                InputHelper::alphanum((string) $request->query->get('object'), false, null, ['_']),
                InputHelper::alphanum((string) $request->query->get('field'), false, null, ['_']),
                InputHelper::clean((string) $request->query->get('q')),
                (int) $request->query->get('limit', 20)
            )]);
        } catch (\OutOfBoundsException) {
            return new JsonResponse(['values' => []], 404);
        }
    }
}
