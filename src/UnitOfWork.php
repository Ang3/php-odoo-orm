<?php

namespace Ang3\Component\Odoo\ORM;

use Ang3\Component\Odoo\ORM\Exception\LogicException;
use Ang3\Component\Odoo\ORM\Exception\RecordNotFoundException;
use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Ang3\Component\Odoo\ORM\Internal\ProxyFactory;
use Ang3\Component\Odoo\ORM\Mapping\ClassMetadata;
use Ang3\Component\Odoo\ORM\Mapping\MappingException;
use Ang3\Component\Odoo\ORM\Mapping\PropertyMetadata;
use Ang3\Component\Odoo\ORM\Model\Collection;
use Ang3\Component\Odoo\ORM\Normalizer\CollectionNormalizer;
use Ang3\Component\Odoo\ORM\Normalizer\DomainStringNormalizer;
use Ang3\Component\Odoo\ORM\Normalizer\RecordNormalizer;
use Ang3\Component\Odoo\ORM\Schema\Model;
use ProxyManager\Proxy\GhostObjectInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Serializer;

class UnitOfWork
{
    private $objectManager;
    private $serializer;
    private $proxyFactory;

    /**
     * @var array
     */
    private $objectData = [];

    /**
     * @var array
     */
    private $recordData = [];

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->serializer = new Serializer([
            new CollectionNormalizer(),
            new DomainStringNormalizer(),
            new RecordNormalizer($objectManager),
        ]);
        $this->proxyFactory = new ProxyFactory();
    }

    /**
     * @throws RuntimeException on persistence error
     */
    public function persist(object $object): void
    {
        if (($object instanceof GhostObjectInterface) && !$object->isProxyInitialized()) {
            return;
        }

        $classMetadata = $this->objectManager->getClassMetadata($object);
        $model = $this->getModel($classMetadata);

        $idProperty = $this->getIdentifierProperty($classMetadata);
        $id = $idProperty->getValue($object);
        $data = $this->normalize($object);

        if (!$data) {
            return;
        }

        if ($id) {
            $oldData = $this->objectData[spl_object_id($object)] ?? [];

            foreach ($data as $fieldName => $value) {
                $propertyMetadata = $classMetadata->resolveProperty((string) $fieldName);

                if (!$propertyMetadata) {
                    continue;
                }

                $field = $propertyMetadata->getField();
                [$oldValue, $newValue] = [
                    $oldData[$fieldName] ?? false,
                    $propertyMetadata->getValue($object),
                ];

                if ($field->isMultipleAssociation()) {
                    if (!$newValue) {
                        unset($data[$fieldName]);
                    }

                    continue;
                }

                if ($newValue === $oldValue) {
                    unset($data[$fieldName]);
                    continue;
                }
            }

            if (0 === count($data)) {
                return;
            }

            $this->objectManager
                ->getClient()
                ->update($model->getName(), $id, $data);
        } else {
            $id = $this->objectManager
                ->getClient()
                ->create($model->getName(), $data);

            $idProperty->setValue($object, $id);
        }

        $this->refresh($object);
    }

    public function delete(object $object): void
    {
        $classMetadata = $this->objectManager->getClassMetadata($object);
        $model = $this->getModel($classMetadata);
        $idProperty = $this->getIdentifierProperty($classMetadata);
        $id = $idProperty->getValue($object);

        $this->objectManager
            ->getClient()
            ->delete($model->getName(), $id);

        $idProperty->setValue($object, null);
    }

    public function createCollection(ClassMetadata $classMetadata, array $ids = []): Collection
    {
        $collection = new Collection($ids);

        foreach ($ids as $id) {
            if (is_int($id)) {
                $record = $this->createObjectProxy($classMetadata, $id);
                $collection->add($record);
            }
        }

        return $collection;
    }

    /**
     * @throws LogicException          when the object is not persisted yet
     * @throws RecordNotFoundException when the record was not found
     */
    public function refresh(object $object): void
    {
        $classMetadata = $this->objectManager->getClassMetadata($object);

        $id = $this
            ->getIdentifierProperty($classMetadata)
            ->getValue($object);

        if (!is_int($id)) {
            throw new RecordNotFoundException(sprintf('Unable to refresh object of type %s because it was not persisted yet', $classMetadata->getClassName()));
        }

        $record = $this->getRecord($classMetadata, $id);
        $this->denormalize($record, $classMetadata, $object);
    }

    public function createObjectProxy(ClassMetadata $classMetadata, int $id): GhostObjectInterface
    {
        $unitOfWork = $this;

        $initializer = function (
            GhostObjectInterface $ghostObject, string $method, array $parameters, &$initializer, array $properties
        ) use ($unitOfWork) {
            $initializer = null;
            $unitOfWork->refresh($ghostObject);

            return true;
        };

        $idProperty = $classMetadata->getIdProperty();
        $proxyOptions = [];

        if ($idProperty) {
            $idProperty = $this->objectManager::getReflector()
                ->getProperty($classMetadata->getClassName(), $idProperty->getPropertyName());

            $proxyOptions['skippedProperties'] = [
                $this->proxyFactory->generatePropertyFqcn($idProperty),
            ];
        }

        $object = $this->proxyFactory->createProxy($classMetadata->getClassName(), $initializer, $proxyOptions);
        $idProperty->setValue($object, $id);

        return $object;
    }

    /**
     * @throws RuntimeException when normalization failed
     */
    public function normalize(object $object): array
    {
        if (($object instanceof GhostObjectInterface) && !$object->isProxyInitialized()) {
            $classMetadata = $this->objectManager->getClassMetadata($object);

            return [
                'id' => $this
                    ->getIdentifierProperty($classMetadata)
                    ->getValue($object),
            ];
        }

        try {
            $record = $this->serializer->normalize($object);

            if (!is_array($record)) {
                throw new RuntimeException(sprintf('Expected record of type array, %s given', gettype($record)));
            }

            return $record;
        } catch (SerializerException $e) {
            throw new RuntimeException(sprintf('Failed to normalize object of type %s', $this->objectManager->getClassMetadata($object)->getClassName()), 0, $e);
        }
    }

    /**
     * @throws RuntimeException when denormalization failed
     */
    public function denormalize(array $data, ClassMetadata $classMetadata, object $objectToPopulate = null): object
    {
        if ($id = ($data['id'] ?? null)) {
            $this->recordData[$classMetadata->getClassName()][$id] = $data;
        }

        try {
            $record = $this->serializer->denormalize($data, $classMetadata->getClassName(), null, [
                RecordNormalizer::OBJECT_TO_POPULATE => $objectToPopulate,
            ]);

            if (!is_object($record)) {
                throw new RuntimeException(sprintf('Expected record of type object, %s given', gettype($record)));
            }

            $this->objectData[spl_object_id($record)] = $data;

            return $record;
        } catch (SerializerException $e) {
            throw new RuntimeException(sprintf('Failed to denormalize data for class %s', $classMetadata->getClassName()), 0, $e);
        }
    }

    /**
     * @throws RecordNotFoundException when the record was not found
     */
    public function getRecord(ClassMetadata $classMetadata, int $id): array
    {
        $record = $this->loadRecord($classMetadata, $id);

        if (!$record) {
            throw RecordNotFoundException::create($classMetadata->getClassName(), $id);
        }

        return $record;
    }

    public function loadRecord(ClassMetadata $classMetadata, int $id): ?array
    {
        $model = $this->getModel($classMetadata);

        return $this->objectManager
            ->getClient()
            ->find($model->getName(), $id, ['fields' => $classMetadata->getFieldNames()]);
    }

    public function getTargetClassMetadata(PropertyMetadata $propertyMetadata): ClassMetadata
    {
        return $this->resolveClassMetadata($this->getTargetModel($propertyMetadata));
    }

    /**
     * @throws MappingException when the property field is not on *ToMany association
     */
    public function getTargetModel(PropertyMetadata $propertyMetadata): Model
    {
        $field = $propertyMetadata->getField();
        $targetModelName = $field->getTargetModel();

        if (!$targetModelName) {
            throw new LogicException(sprintf('Cannot load collection for property %s because the related field is neither of type "oneToMany" nor "manyToMany" (field type: "%s")', $propertyMetadata->getFullName(), $field->getType()));
        }

        return $this->objectManager
            ->getSchema()
            ->getModel($targetModelName);
    }

    /**
     * @throws MappingException when the model was not found on Odoo database
     */
    public function getModel(ClassMetadata $classMetadata): Model
    {
        $modelName = $classMetadata->getModelName();

        if (!$modelName) {
            throw MappingException::classNotSupported($classMetadata->getClassName());
        }

        return $this->objectManager
            ->getSchema()
            ->getModel($modelName);
    }

    /**
     * @throws MappingException when no class found for the model
     */
    public function resolveClassMetadata(Model $model): ClassMetadata
    {
        $classMetadata = $this->objectManager
            ->getClassMetadataFactory()
            ->getClassMetadataRegistry()
            ->resolve($model->getName());

        if (!$classMetadata) {
            throw MappingException::modelNotSupported($model->getName());
        }

        return $classMetadata;
    }

    /**
     * @throws MappingException when the class has no Odoo identifier
     */
    public function getIdentifierProperty(ClassMetadata $classMetadata): PropertyMetadata
    {
        $idProperty = $classMetadata->getIdProperty();

        if (!$idProperty) {
            throw MappingException::identifierNotFound($classMetadata);
        }

        return $idProperty;
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }

    public function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    public function getProxyFactory(): ProxyFactory
    {
        return $this->proxyFactory;
    }
}
