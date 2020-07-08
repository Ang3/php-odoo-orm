<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use function Symfony\Component\String\s;
use Symfony\Contracts\Cache\CacheInterface;

class MetadataCache
{
    public const CLASS_METADATA_REGISTRY_CACHE_KEY = '%s.class_map';
    public const CLASS_METADATA_CACHE_KEY = '%s.class_metadata.%s';

    /**
     * @var ClassMetadataFactory
     */
    private $classMetadataFactory;

    /**
     * @var string[]|null
     */
    private $classMap;

    public function __construct(ClassMetadataFactory $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * @throws RuntimeException on cache errors
     */
    public function getClassMetadata(ReflectionClass $reflectionClass, callable $loader): ClassMetadata
    {
        $cacheKey = $this->getClassMetadataCacheKey($reflectionClass);

        try {
            return $this
                ->getCache()
                ->get($cacheKey, $loader);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(sprintf('Failed to retrieve class metadata cache entry at key "%s"', $cacheKey), 0, $e);
        }
    }

    /**
     * @throws MappingException when the model is already mapped by another class.
     */
    public function registerModelClassMetadata(string $modelName, ReflectionClass $reflectionClass): void
    {
        $classMap = $this->classMap;

        if (isset($classMap[$modelName])) {
            throw MappingException::duplicateModel($modelName, $reflectionClass->getName(), $this->classMetadataFactory->getClassMetadata($reflectionClass));
        }

        $classMap[$modelName] = $reflectionClass->getName();

        $metadataCache = $this->getCache();
        $cacheKey = $this->getClassMapCacheKey();

        try {
            $metadataCache->delete($cacheKey);
        } catch (InvalidArgumentException $e) {
        }

        try {
            $this->classMap = $metadataCache->get($cacheKey, function () use ($classMap) {
                return $classMap;
            });
        } catch (InvalidArgumentException $e) {
        }
    }

    public function resolveModelClassMetadata(string $modelName): ?string
    {
        $classMap = $this->getClassMap();

        return $classMap[$modelName] ?? null;
    }

    /**
     * @throws RuntimeException on cache errors
     */
    public function getClassMap(): array
    {
        if (null === $this->classMap) {
            $cacheKey = $this->getClassMapCacheKey();

            try {
                $this->classMap = $this
                    ->getCache()
                    ->get($cacheKey, function () {
                        return [];
                    });
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException(sprintf('Failed to get Odoo classes from cache at key "%s"', $cacheKey), 0, $e);
            }
        }

        return $this->classMap;
    }

    public function getClassMetadataCacheKey(ReflectionClass $reflectionClass): string
    {
        return sprintf(self::CLASS_METADATA_CACHE_KEY, $this->classMetadataFactory
            ->getObjectManager()
            ->getClient()
            ->getIdentifier(), s($reflectionClass->getName())
                ->replaceMatches('#[^a-zA-Z0-9_]+#', '_')
                ->toString()
        );
    }

    public function getClassMapCacheKey(): string
    {
        return sprintf(self::CLASS_METADATA_REGISTRY_CACHE_KEY, $this->classMetadataFactory
            ->getObjectManager()
            ->getClient()
            ->getIdentifier());
    }

    /**
     * @internal
     */
    private function getCache(): CacheInterface
    {
        return $this->classMetadataFactory
            ->getObjectManager()
            ->getConfiguration()
            ->getMetadataCache();
    }

    public function getClassMetadataFactory(): ClassMetadataFactory
    {
        return $this->classMetadataFactory;
    }
}
