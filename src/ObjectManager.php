<?php

namespace Ang3\Component\Odoo\ORM;

use Ang3\Component\Odoo\Client;
use Ang3\Component\Odoo\Expression\ExpressionBuilder;
use Ang3\Component\Odoo\ORM\Exception\RecordNotFoundException;
use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use Ang3\Component\Odoo\ORM\Mapping\ClassMetadata;
use Ang3\Component\Odoo\ORM\Mapping\ClassMetadataFactory;
use Ang3\Component\Odoo\ORM\Schema\Schema;
use Doctrine\Common\Annotations\Reader;

class ObjectManager
{
    use ReflectorAwareTrait;

    private $client;
    private $schema;
    private $configuration;
    private $classMetadataFactory;
    private $unitOfWork;

    public function __construct(Client $client, Configuration $configuration, Reader $reader)
    {
        $this->client = $client;
        $this->configuration = $configuration;
        $this->schema = new Schema($this);
        $this->classMetadataFactory = new ClassMetadataFactory($this, $reader);
        $this->unitOfWork = new UnitOfWork($this);
    }

    public function persist(object $object): void
    {
        $this->unitOfWork->persist($object);
    }

    public function delete(object $object): void
    {
        $this->unitOfWork->delete($object);
    }

    public function refresh(object $object): void
    {
        $this->unitOfWork->refresh($object);
    }

    /**
     * @throws RecordNotFoundException when the record was not found
     */
    public function get(string $class, int $id): object
    {
        return $this
            ->getRepository($class)
            ->get($id);
    }

    public function find(string $class, int $id): ?object
    {
        return $this
            ->getRepository($class)
            ->find($id);
    }

    /**
     * @param mixed $subject
     */
    public function getRepository($subject): ObjectRepository
    {
        $classMetadata = $this->getClassMetadata($subject);
        $repositoryClass = $classMetadata->getRepositoryClass() ?: ObjectRepository::class;

        /** @var ObjectRepository $repository */
        $repository = self::getReflector()
            ->getClass($repositoryClass)
            ->newInstanceArgs([$this, $classMetadata]);

        return $repository;
    }

    /**
     * @param mixed $subject
     */
    public function getClassMetadata($subject): ClassMetadata
    {
        return $this->classMetadataFactory->getClassMetadata($subject);
    }

    /**
     * @param mixed $subject
     */
    public function supports($subject): bool
    {
        return null !== $this->classMetadataFactory
            ->getClassMetadata($subject)
            ->getModelName();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getClassMetadataFactory(): ClassMetadataFactory
    {
        return $this->classMetadataFactory;
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getExpressionBuilder(): ExpressionBuilder
    {
        return $this
            ->getClient()
            ->getExpressionBuilder();
    }
}
