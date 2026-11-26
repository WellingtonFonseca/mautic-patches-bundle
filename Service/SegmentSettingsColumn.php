<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service;

use Mautic\LeadBundle\Entity\LeadList;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The read-only twin of the right column of the segment edit form (SegmentDistinctTabSubscriber): the "Configurações" block (category,
 * visible to others, preference center, published) and the "Distinct" block, from the SAVED data (the segment and SegmentDistinctStore).
 * No form, no inputs. One component for the segment page (GET /s/segment-settings/{id}) and the details modal of the segment list.
 */
class SegmentSettingsColumn
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        private SegmentDistinctStore $store,
        private IndexedLeadFields $indexedFields
    ) {
    }

    public function html(LeadList $segment): string
    {
        $config  = $this->store->get($segment->getId());
        $labels  = $this->indexedFields->all();
        $fields  = [];
        foreach ($config->fields ?? [] as $alias) {
            $fields[] = isset($labels[$alias]) ? $labels[$alias].' ('.$alias.')' : $alias;
        }

        return $this->twig->render('@MauticPatches/Segment/settings_column.html.twig', [
            'category'    => $segment->getCategory()?->getTitle(),
            'toggles'     => [
                $this->toggle('mautic.lead.list.form.isglobal', $segment->isGlobal(), 'mautic.lead.list.form.isglobal.tooltip'),
                $this->toggle('mautic.lead.list.form.isPreferenceCenter', (bool) $segment->getIsPreferenceCenter(), 'mautic.lead.list.form.isPreferenceCenter.tooltip'),
                $this->toggle('mautic.core.form.active', $segment->isPublished(), null),
            ],
            'distinct'    => [
                $this->toggle('mautic.patches.segment.distinct.enabled', $config->enabled ?? false, 'mautic.patches.segment.distinct.enabled.help'),
                $this->toggle('mautic.patches.segment.distinct.remove_empty', $config->removeEmpty ?? false, 'mautic.patches.segment.distinct.remove_empty.help'),
            ],
            'fieldsLabel' => $this->translator->trans('mautic.patches.segment.distinct.fields'),
            'fields'      => $fields,
        ]);
    }

    /** @return array{label: string, value: string, help: string} */
    private function toggle(string $label, bool $on, ?string $help): array
    {
        return [
            'label' => trim($this->translator->trans($label)),
            'value' => $this->translator->trans($on ? 'mautic.core.yes' : 'mautic.core.no'),
            'help'  => null === $help ? '' : trim($this->translator->trans($help)),
        ];
    }
}
