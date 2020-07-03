<?php

namespace Ang3\Component\Odoo\ORM\Normalizer;

use Ang3\Component\Odoo\Expression\DomainInterface;
use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Ang3\Component\Odoo\ORM\Model\Collection;
use Ang3\Component\Odoo\ORM\ObjectManager;
use Ang3\Component\Odoo\ORM\Schema\Field;
use DateTimeInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\SerializerAwareTrait;

class RecordNormalizer extends AbstractNormalizer implements ContextAwareDenormalizerInterface
{
    use SerializerAwareTrait;

    /**
     * Context parameters.
     */
    public const OBJECT_TO_POPULATE = 'object_to_populate';
    public const OLD_DATA = 'old_data';

    private $objectManager;
    private $propertyAccessor;
    private $dateTimeNormalizer;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->propertyAccessor = new PropertyAccessor(true, true);
        $this->dateTimeNormalizer = new DateTimeNormalizer([
            DateTimeNormalizer::TIMEZONE_KEY => 'UTC',
        ]);
        $this->defaultContext = array_merge($this->defaultContext, [
            self::OBJECT_TO_POPULATE => null,
            self::OLD_DATA => [],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException when normalized failed
     *
     * @param object $object
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        $context = array_merge($this->defaultContext, $context);
        $context[self::ODOO_NORMALIZATION_CONTEXT] = true;

        $unitOfWork = $this->objectManager->getUnitOfWork();
        $classMetadata = $this->objectManager->getClassMetadata($object);
        $oldData = $context[self::OLD_DATA] ?? [];
        $data = [];

        foreach ($classMetadata->getPropertiesIterator() as $propertyMetadata) {
            $field = $propertyMetadata->getField();

            if ($field->isReadOnly()) {
                continue;
            }

            $value = $propertyMetadata->getValue($object);

            if ($value instanceof DomainInterface) {
                $value = $this->normalizeObject($value, $context);
            }

            try {
                switch ($field->getType()) {
                    case Field::T_BOOLEAN:
                        $value = (bool) $value;
                        break;

                    case Field::T_INTEGER:
                        $value = null !== $value ? (int) $value : false;
                        break;

                    case Field::T_FLOAT:
                    case Field::T_MONETARY:
                        $value = null !== $value ? (float) $value : false;
                        break;

                    case Field::T_BINARY:
                    case Field::T_CHAR:
                    case Field::T_HTML:
                    case Field::T_SELECTION:
                    case Field::T_TEXT:
                        $value = null !== $value ? (string) $value : false;
                        break;

                    case Field::T_DATE:
                    case Field::T_DATETIME:
                        if ($value instanceof DateTimeInterface) {
                            $value = $this->dateTimeNormalizer->normalize($value, null, [
                                DateTimeNormalizer::FORMAT_KEY => $field->getDateFormat(),
                            ]);
                        } else {
                            $value = null !== $value ? (string) $value : false;
                        }
                        break;

                    case Field::T_MANY_TO_ONE:
                        if (is_object($value)) {
                            $targetClassMetadata = $unitOfWork->getTargetClassMetadata($propertyMetadata);
                            $id = $unitOfWork
                                ->getIdentifierProperty($targetClassMetadata)
                                ->getValue($value);

                            if (!$id) {
                                throw new RuntimeException(sprintf('Cannot normalize property %s because the embedded object of type %s is not persisted yet', $propertyMetadata->getFullName(), $targetClassMetadata->getClassName()));
                            }

                            $value = $id;
                        } else {
                            $value = $value['id'] ?? $value[0] ?? null;
                            $value = $value ? (int) $value : false;
                        }
                        break;

                    case Field::T_MANY_TO_MANY:
                    case Field::T_ONE_TO_MANY:
                        $values = array_filter((array) $value);

                        if (!$values) {
                            continue 2;
                        }

                        $value = $value instanceof Collection ? $this->normalizeObject($value, $context) : $values;

                        if (!$value) {
                            continue 2;
                        }
                        break;
                }
            } catch (\Throwable $e) {
                $actualType = is_object($value) ? get_class($value) : gettype($value);
                throw new RuntimeException(sprintf('Failed to convert PHP value of type %s for property %s and field type "%s"', $actualType, $propertyMetadata->getFullName(), $field->getType()), 0, $e);
            }

            $oldValue = $oldData[$field->getName()] ?? null;

            if (!$field->isMultipleAssociation() && null !== $oldValue) {
                if ($field->isSingleAssociation()) {
                    if (!is_int($oldValue)) {
                        $oldValue = $oldValue[0] ?? false;
                    }
                }

                if ($oldValue === $value) {
                    continue;
                }
            }

            $data[$field->getName()] = $value;
        }

        return $data;
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return is_object($data) && $this->objectManager->supports($data);
    }

    /**
     * @param mixed  $data
     * @param string $type
     */
    public function denormalize($data, $type, string $format = null, array $context = []): ?object
    {
        if (!$data) {
            return null;
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('Expected data of type array|bool, %s given', gettype($data)));
        }

        $context = array_merge($this->defaultContext, $context);
        $object = $context[self::OBJECT_TO_POPULATE] ?: $this
            ->getClass($type)
            ->newInstance();

        $classMetadata = $this->objectManager->getClassMetadata($type);
        $unitOfWork = $this->objectManager->getUnitOfWork();

        foreach ($data as $fieldName => $value) {
            $propertyMetadata = $classMetadata->resolveProperty($fieldName);

            if (!$propertyMetadata) {
                continue;
            }

            $field = $propertyMetadata->getField();

            if ($field->isIdentifier()) {
                $propertyMetadata->setValue($object, $value);
                continue;
            }

            try {
                switch ($field->getType()) {
                    case Field::T_BOOLEAN:
                        $value = (bool) $value;
                        break;

                    case Field::T_INTEGER:
                        $value = false !== $value ? (int) $value : null;
                        break;

                    case Field::T_FLOAT:
                    case Field::T_MONETARY:
                        $value = false !== $value ? (float) $value : null;
                        break;

                    case Field::T_BINARY:
                    case Field::T_CHAR:
                    case Field::T_HTML:
                    case Field::T_SELECTION:
                    case Field::T_TEXT:
                        $value = false !== $value ? (string) $value : null;
                        break;

                    case Field::T_DATE:
                    case Field::T_DATETIME:
                        if (!($value instanceof \DateTimeInterface)) {
                            $context = [
                                DateTimeNormalizer::FORMAT_KEY => $field->getDateFormat(),
                            ];

                            $value = $value ? $this->dateTimeNormalizer->denormalize($value, \DateTimeImmutable::class, null, $context) : null;
                        }
                        break;

                    case Field::T_MANY_TO_ONE:
                        $value = is_array($value) ? (int) $value[0] : null;

                        if ($value) {
                            $value = $unitOfWork->createObjectProxy($unitOfWork->getTargetClassMetadata($propertyMetadata), $value);
                        }
                        break;

                    case Field::T_MANY_TO_MANY:
                    case Field::T_ONE_TO_MANY:
                        $value = $unitOfWork->createCollection($unitOfWork->getTargetClassMetadata($propertyMetadata), array_filter((array) $value));
                        $propertyMetadata->setValue($object, $value);
                        continue 2;
                }
            } catch (\Throwable $e) {
                $actualType = is_object($value) ? get_class($value) : gettype($value);
                throw new RuntimeException(sprintf('Failed to convert Odoo value of type %s to PHP value for property %s and field type "%s"', $actualType, $propertyMetadata->getFullName(), $field->getType()), 0, $e);
            }

            $this->propertyAccessor->setValue($object, $propertyMetadata->getPropertyName(), $value);
            unset($data[$fieldName]);
        }

        return $object;
    }

    /**
     * @param mixed  $data
     * @param string $type
     */
    public function supportsDenormalization($data, $type, string $format = null, array $context = []): bool
    {
        return is_array($data) && $this->objectManager->supports($type);
    }
}
