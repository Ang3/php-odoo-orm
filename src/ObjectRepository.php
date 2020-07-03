<?php

namespace Ang3\Component\Odoo\ORM;

use Ang3\Component\Odoo\Expression\DomainInterface;
use Ang3\Component\Odoo\Expression\ExpressionBuilder;
use Ang3\Component\Odoo\ORM\Mapping\ClassMetadata;
use Ang3\Component\Odoo\ORM\Schema\Model;
use DateTimeInterface;

class ObjectRepository
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var ClassMetadata
     */
    protected $classMetadata;

    /**
     * @var Model
     */
    protected $model;

    /**
     * @var ExpressionBuilder
     */
    protected $expr;

    /**
     * @param object|string $subject
     */
    public function __construct(ObjectManager $objectManager, $subject)
    {
        $this->objectManager = $objectManager;
        $this->classMetadata = $objectManager->getClassMetadata($subject);
        $this->model = $objectManager
            ->getUnitOfWork()
            ->getModel($this->classMetadata);
        $this->expr = $objectManager
            ->getClient()
            ->getExpressionBuilder();
    }

    public function get(int $id): object
    {
        $unitOfWork = $this->objectManager->getUnitOfWork();
        $record = $unitOfWork->getRecord($this->classMetadata, $id);

        return $unitOfWork->denormalize($record, $this->classMetadata);
    }

    public function find(int $id): ?object
    {
        $unitOfWork = $this->objectManager->getUnitOfWork();
        $record = $unitOfWork->loadRecord($this->classMetadata, $id);

        return $unitOfWork->denormalize($record, $this->classMetadata);
    }

    public function findOneBy(DomainInterface $domain, array $orders = [], int $offset = 0): ?object
    {
        $records = $this->findBy($domain, $orders, 1, $offset);

        return array_shift($records);
    }

    public function findAll(array $orders = [], int $limit = null, int $offset = 0): array
    {
        return $this->findBy(null, $orders, $limit, $offset);
    }

    public function findBy(DomainInterface $domain = null, array $orders = [], int $limit = null, int $offset = 0): array
    {
        $unitOfWork = $this->objectManager->getUnitOfWork();
        $records = $this->objectManager
            ->getClient()
            ->findBy($this->getModelName(), $domain, $this->prepareOptions($orders, $limit, $offset));

        foreach ($records as $key => $record) {
            if (!is_array($record)) {
                unset($records[$key]);
                continue;
            }

            $records[$key] = $unitOfWork->denormalize($record, $this->classMetadata);
        }

        return $records;
    }

    /**
     * @return int[]
     */
    public function searchAll(array $orders = [], int $limit = null, int $offset = 0): array
    {
        return $this->search(null, $orders, $limit, $offset);
    }

    public function searchOne(DomainInterface $domain, array $orders = [], int $offset = 0): ?int
    {
        $records = $this->search($domain, $orders, 1, $offset);

        return array_shift($records);
    }

    /**
     * @return int[]
     */
    public function search(DomainInterface $domain = null, array $orders = [], int $limit = null, int $offset = 0): array
    {
        return $this->objectManager
            ->getClient()
            ->search($this->getModelName(), $domain, $this->prepareOptions($orders, $limit, $offset));
    }

    public function count(DomainInterface $domain = null): int
    {
        return $this->objectManager
            ->getClient()
            ->count($this->getModelName(), $domain);
    }

    public function exists(int $id): bool
    {
        return $this->objectManager
            ->getClient()
            ->exists($this->getModelName(), $id);
    }

    protected function prepareOptions(array $orders = [], int $limit = null, int $offset = 0): array
    {
        $options = [];

        if ($fields = $this->classMetadata->getFieldNames()) {
            if (count($fields) > 1 || 'id' !== $fields[0]) {
                $options['fields'] = $fields;
            }
        }

        if ($orders) {
            foreach ($orders as $key => $value) {
                $fieldName = is_int($key) ? (string) $value : $key;
                $value = strtolower((string) $value);
                $order = 'desc' === $value ? 'desc' : 'asc';
                $orders[$key] = sprintf('%s %s', $fieldName, $order);
            }

            $options['order'] = implode(', ', array_values($orders));
        }

        if ($limit) {
            $options['limit'] = $limit;
        }

        if ($offset) {
            $options['offset'] = $offset;
        }

        return $options;
    }

    public function formatDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function formatDateTime(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }

    public function getClassMetadata(): ClassMetadata
    {
        return $this->classMetadata;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getModelName(): string
    {
        return $this->model->getName();
    }
}
