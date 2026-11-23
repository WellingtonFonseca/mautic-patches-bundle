<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use Doctrine\ORM\EntityManagerInterface;

/**
 * The custom objects of this instance by id and by name. The permission key "custom_objects:<id>" carries an id that is only valid
 * here, so a package carries the name too. Custom Objects is optional: without it there are none.
 */
class CustomObjectCatalog
{
    private const ENTITY = 'MauticPlugin\\CustomObjectsBundle\\Entity\\CustomObject';

    /** @var array<int, string>|null */
    private ?array $names = null;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<int, string> id => name
     */
    public function names(): array
    {
        if (null !== $this->names) {
            return $this->names;
        }
        $this->names = [];
        if (class_exists(self::ENTITY)) {
            foreach ($this->em->getRepository(self::ENTITY)->findAll() as $object) {
                $this->names[(int) $object->getId()] = (string) $object->getName();
            }
        }

        return $this->names;
    }

    public function idByName(string $name): ?int
    {
        $id = array_search($name, $this->names(), true);

        return false === $id ? null : (int) $id;
    }
}
