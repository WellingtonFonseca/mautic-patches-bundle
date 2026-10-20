<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Form\Type;

use MauticPlugin\MauticPatchesBundle\Service\ContactFields\ContactFieldsCatalog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One criterion (field, operator, value) of the condition "Contact fields". The condition holds its first criterion in its
 * own field / operator / value and the others in `additionalCriteria`, which use this type.
 *
 * The field select carries, per option, what the page script needs to follow the field: its operators, its options (a list
 * field), its type and its object (contact or company).
 */
class ContactFieldsCriterionType extends AbstractType
{
    public function __construct(
        private ContactFieldsCatalog $catalog,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @param mixed[] $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addCriterionFields($builder, $options['data']['field'] ?? null);

        $builder->add('value', TextType::class, [
            'required' => false,
            'label'    => 'mautic.patches.condition.contact_fields.value',
            'attr'     => ['class' => 'form-control cf-criterion-value'],
        ]);
    }

    /**
     * The field and operator controls (the value differs: required on the first criterion).
     */
    public function addCriterionFields(FormBuilderInterface $builder, ?string $selected = null): void
    {
        $fields = $this->catalog->fields();
        $groups = [];
        $attrs  = [];

        foreach ($fields as $alias => $meta) {
            $groups[$this->translator->trans('mautic.patches.condition.contact_fields.group.'.$meta['object'])][$meta['label']] = $alias;
            $attrs[$alias] = [
                'data-operators'  => json_encode($meta['operators']),
                'data-options'    => json_encode($meta['options'], JSON_FORCE_OBJECT),
                'data-field-type' => $meta['type'],
                'data-object'     => $meta['object'],
            ];
        }

        $builder->add('field', ChoiceType::class, [
            'required'    => true,
            'label'       => 'mautic.patches.condition.contact_fields.field',
            'choices'     => $groups,
            'choice_attr' => static fn ($choice, $key, $value): array => $attrs[$value] ?? [],
            'attr'        => ['class' => 'form-control cf-criterion-field'],
        ]);

        $alias     = null !== $selected && isset($fields[$selected]) ? $selected : (string) array_key_first($fields);
        $operators = [] === $fields ? [] : $fields[$alias]['operators'];

        $builder->add('operator', ChoiceType::class, [
            'required' => true,
            'label'    => 'mautic.patches.condition.contact_fields.operator',
            'choices'  => array_flip($operators),
            'attr'     => ['class' => 'form-control cf-criterion-operator'],
        ]);

        // Each field has its own operators: the list is replaced by the browser, so the choice is not validated against it.
        $builder->get('operator')->resetViewTransformers();
    }
}
