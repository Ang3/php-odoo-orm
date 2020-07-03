<?php

namespace Ang3\Component\Odoo\ORM\Internal;

use ProxyManager\Factory\LazyLoadingGhostFactory;
use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionProperty;

class ProxyFactory
{
    /**
     * Lazy loading proxy factory option keys.
     */
    private const SKIPPED_PROPERTIES = 'skippedProperties';

    /**
     * @var LazyLoadingGhostFactory
     */
    private $lazyLoadingGhostFactory;

    public function __construct(LazyLoadingGhostFactory $lazyLoadingGhostFactory = null)
    {
        $this->lazyLoadingGhostFactory = $lazyLoadingGhostFactory ?: new LazyLoadingGhostFactory();
    }

    /**
     * @param class-string $className
     */
    public function createProxy(string $className, \Closure $initializer, array $options = []): GhostObjectInterface
    {
        return $this->lazyLoadingGhostFactory->createProxy($className, $initializer, $options);
    }

    public function generatePropertyFqcn(ReflectionProperty $property): string
    {
        if ($property->isPrivate()) {
            return "\0".$property->getDeclaringClass()->getName()."\0".$property->getName();
        }

        if ($property->isProtected()) {
            return "\0*\0".$property->getName();
        }

        return $property->getName();
    }

    public function isProxy(object $object): bool
    {
        return $object instanceof GhostObjectInterface;
    }
}
