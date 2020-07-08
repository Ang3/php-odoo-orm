<?php

namespace Ang3\Component\Odoo\ORM\Schema;

use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

class SchemaCache
{
    public const MODEL_CACHE_KEY = '%s.model.%s';

    /**
     * @var Schema
     */
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @throws RuntimeException on cache errors
     */
    public function getModel(string $modelName, callable $loader): Model
    {
        $cacheKey = $this->getModelCacheKey($modelName);

        try {
            return $this
                ->getCache()
                ->get($cacheKey, $loader);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(sprintf('Failed to retrieve model cache entry at key "%s"', $cacheKey), 0, $e);
        }
    }

    public function getModelCacheKey(string $modelName): string
    {
        return sprintf(self::MODEL_CACHE_KEY, $this->schema
            ->getObjectManager()
            ->getClient()
            ->getIdentifier(), $modelName);
    }

    /**
     * @internal
     */
    private function getCache(): CacheInterface
    {
        return $this->schema
            ->getObjectManager()
            ->getConfiguration()
            ->getSchemaCache();
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }
}
