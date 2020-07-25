<?php

namespace Ang3\Component\Odoo\ORM\Normalizer;

use Ang3\Component\Odoo\Expression\DomainInterface;
use Ang3\Component\Odoo\Expression\ExpressionBuilder;

class DomainStringNormalizer extends AbstractNormalizer
{
    private $expressionBuilder;

    public function __construct(ExpressionBuilder $expressionBuilder = null)
    {
        $this->expressionBuilder = $expressionBuilder ?: new ExpressionBuilder();
    }

    /**
     * {@inheritdoc}
     *
     * @param DomainInterface $data
     */
    public function normalize($data, $format = null, array $context = []): string
    {
        $data = $this->expressionBuilder->normalizeDomains($data);
        $data = $data[0];

        foreach ($data as $key => $value) {
            $data[$key] = $this->stringify($value);
        }

        return sprintf('[%s]', implode(', ', $data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return $data instanceof DomainInterface && ($context[self::ODOO_NORMALIZATION_CONTEXT] ?? false);
    }

    /**
     * @internal
     *
     * @param mixed $data
     *
     * @return int|float|string
     */
    private function stringify($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->stringify($value);
            }

            return sprintf('(%s)', implode(', ', $data));
        }

        if (is_bool($data)) {
            return $data ? 'True' : 'False';
        }

        if (is_int($data) || is_float($data)) {
            return $data;
        }

        $value = (string) $data;

        if (is_string($value) && preg_match('#^context:(.*)#', $value, $matches)) {
            return $matches[1];
        }

        return sprintf("'%s'", $value);
    }
}
