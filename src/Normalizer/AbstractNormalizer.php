<?php

namespace Ang3\Component\Odoo\ORM\Normalizer;

use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use ReflectionClass;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

abstract class AbstractNormalizer implements ContextAwareNormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;
    use ReflectorAwareTrait;

    public const ODOO_NORMALIZATION_CONTEXT = 'odoo_normalization_context';
    public const NORMALIZED_OBJECTS = 'normalized_objects';

    /**
     * @var array
     */
    protected $defaultContext = [
        self::ODOO_NORMALIZATION_CONTEXT => true,
        self::NORMALIZED_OBJECTS => [],
    ];

    /**
     * @throws SerializerException        when normalization failed
     * @throws CircularReferenceException when a circular reference is detected
     *
     * @return mixed
     */
    protected function normalizeObject(object $object, array $context = [])
    {
        if (!($this->serializer instanceof NormalizerInterface)) {
            throw new LogicException(sprintf('Cannot normalize object of type %s because the injected serializer is not a normalizer', get_class($object)));
        }

        $context = array_merge($this->defaultContext, $context);
        $objectId = $this->getObjectId($object, $context);
        $context[self::NORMALIZED_OBJECTS][] = $objectId;

        return $this->serializer->normalize($object, null, $context);
    }

    /**
     * @throws CircularReferenceException when a circular reference is detected
     */
    protected function getObjectId(object $object, array $context = []): int
    {
        $objectId = spl_object_id($object);

        if (in_array($objectId, $context[self::NORMALIZED_OBJECTS] ?? [])) {
            throw new CircularReferenceException(sprintf('A circular reference has been detected when serializing the object of class "%s"', self::getReflector()->getClass($object)->getName()));
        }

        return $objectId;
    }

    /**
     * @param mixed $data
     *
     * @throws SerializerException when denormalization failed
     *
     * @return array|object
     */
    protected function denormalizeData($data, string $type, array $context = [])
    {
        if (!($this->serializer instanceof DenormalizerInterface)) {
            throw new LogicException(sprintf('Cannot normalize data of type %s because the injected serializer is not a normalizer', get_class($data)));
        }

        return $this->serializer->denormalize($data, $type, null, $context);
    }

    /**
     * @param mixed $subject
     */
    protected function getClass($subject): ReflectionClass
    {
        return self::getReflector()
            ->getClass($subject);
    }
}
