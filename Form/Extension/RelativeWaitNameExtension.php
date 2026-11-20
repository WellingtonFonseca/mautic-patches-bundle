<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Extension;

use Mautic\CampaignBundle\Form\Type\EventType;
use MauticPlugin\MauticPatchesBundle\EventListener\RelativeWaitSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\RelativeWait\RelativeWaitNameFormatter;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * The Wait node (patches.wait_relative) has no "Nome" field: its name is "Esperar / 2 dias / depois de / Data de nascimento", set here on submit so the canvas
 * (which keeps a new event in the session until the campaign is saved) already shows it. Other node types are not touched.
 */
class RelativeWaitNameExtension extends AbstractTypeExtension
{
    public function __construct(private RelativeWaitNameFormatter $formatter)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [EventType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'] ?? null;
        if (RelativeWaitSubscriber::TYPE !== (is_array($data) ? ($data['type'] ?? null) : null)) {
            return;
        }

        $builder->add('name', HiddenType::class, ['required' => false]);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }
            $properties   = is_array($data['properties'] ?? null) ? $data['properties'] : null;
            $data['name'] = $this->formatter->fromProperties($properties);
            $event->setData($data);
        });
    }
}
