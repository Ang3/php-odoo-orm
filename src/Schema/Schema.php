<?php

namespace Ang3\Component\Odoo\ORM\Schema;

use Ang3\Component\Odoo\ORM\ObjectManager;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

class Schema
{
    public const IR_MODEL = 'ir.model';
    public const IR_MODEL_FIELDS = 'ir.model.fields';
    public const IR_MODEL_FIELD_SELECTION = 'ir.model.fields.selection';

    /**
     * Cache keys.
     */
    public const CACHE_PREFIX = 'odoo.schema';
    public const GET_MODEL_CACHE_KEY = self::CACHE_PREFIX.'.ir_model_by_name.%s';

    private $objectManager;
    private $propertyNormalizer;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->propertyNormalizer = new PropertyNormalizer();
    }

    /**
     * @throws SchemaException  when the model was not found
     * @throws RuntimeException on cache errors
     */
    public function getModel(string $modelName): Model
    {
        $cacheKey = sprintf(self::GET_MODEL_CACHE_KEY, $modelName);
        $client = $this->objectManager->getClient();
        $normalizer = $this->propertyNormalizer;

        try {
            return $this->objectManager
                ->getConfiguration()
                ->getSchemaCache()
                ->get($cacheKey, static function () use ($client, $normalizer, $modelName) {
                    $model = $client->findOneBy(self::IR_MODEL, $client->expr()
                        ->eq('model', $modelName)
                    );

                    if (!$model) {
                        throw SchemaException::modelNotFound($modelName);
                    }

                    $fields = $client->findBy(self::IR_MODEL_FIELDS, $client->expr()
                        ->eq('model_id', $model['id'])
                    );

                    $model['fields'] = [];

                    foreach ($fields as $key => $data) {
                        $choices = [];

                        $selectionsIds = array_filter($data['selection_ids'] ?? []);

                        if (!empty($selectionsIds)) {
                            $choices = $client->findBy(self::IR_MODEL_FIELD_SELECTION, $client->expr()
                                ->eq('field_id', $data['id'])
                            );

                            foreach ($choices as $index => $choice) {
                                if (is_array($choice)) {
                                    $choices[$index] = $normalizer->denormalize([
                                        'id' => (int) $choice['id'],
                                        'name' => (string) $choice['name'],
                                        'value' => $choice['value'],
                                    ], Choice::class);
                                }
                            }
                        } elseif (!empty($data['selection'])) {
                            if (preg_match_all('#^\[\s*(\(\'(\w+)\'\,\s*\'(\w+)\'\)\s*\,?\s*)*\s*\]$#', trim($data['selection']), $matches, PREG_SET_ORDER)) {
                                foreach ($matches as $match) {
                                    if (isset($match[2], $match[3])) {
                                        $choices[] = $normalizer->denormalize([
                                            'name' => $match[3],
                                            'value' => $match[2],
                                        ], Choice::class);
                                    }
                                }
                            }
                        }

                        /** @var Selection|null $selection */
                        $selection = $choices ? $normalizer->denormalize([
                            'choices' => $choices,
                        ], Selection::class) : null;

                        /** @var Field $field */
                        $field = $normalizer->denormalize([
                            'id' => (int) $data['id'],
                            'name' => (string) $data['name'],
                            'displayName' => (string) $data['display_name'],
                            'type' => (string) $data['ttype'],
                            'size' => $data['size'],
                            'selection' => $selection,
                            'targetModel' => $data['relation'] ?: null,
                            'mappedBy' => $data['relation_field'] ?: null,
                            'required' => (bool) $data['required'],
                            'readOnly' => (bool) $data['readonly'],
                        ], Field::class);

                        $model['fields'][$field->getName()] = $field;
                        unset($fields[$key]);
                    }

                    /** @var Model $model */
                    $model = $normalizer->denormalize([
                        'id' => (int) $model['id'],
                        'name' => (string) $model['model'],
                        'displayName' => (string) $model['name'],
                        'transient' => (bool) $model['transient'],
                        'fields' => $model['fields'] ?? [],
                    ], Model::class);

                    return $model;
                });
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(sprintf('Failed to get cache entry "%s"', $cacheKey), 0, $e);
        }
    }
}
