<?php

namespace Ang3\Component\Odoo\ORM;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class Configuration
{
    /**
     * @var CacheInterface
     */
    private $schemaCache;

    /**
     * @var CacheInterface
     */
    private $metadataCache;

    public function __construct(CacheInterface $schemaCache = null, CacheInterface $metadataCache = null)
    {
        $this->schemaCache = $schemaCache ?: new ArrayAdapter(0, true);
        $this->metadataCache = $metadataCache ?: new ArrayAdapter(0, true);
    }

    public function getSchemaCache(): CacheInterface
    {
        return $this->schemaCache;
    }

    public function setSchemaCache(CacheInterface $schemaCache): void
    {
        $this->schemaCache = $schemaCache;
    }

    public function getMetadataCache(): CacheInterface
    {
        return $this->metadataCache;
    }

    public function setMetadataCache(CacheInterface $metadataCache): void
    {
        $this->metadataCache = $metadataCache;
    }
}
