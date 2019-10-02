<?php

namespace Normalt\Normalizer;

use ReflectionObject;

/**
 * This uses reflection to run a recursive normalization on every property
 * that is found, if the property contains an array each member of that
 * array will be normalized if a normalizer for it is found.
 *
 * If an object is found that is not supported the the process will be aborted
 * and an exception will be raised.
 *
 * When deserializing it is important that each denormalizer knows when an array
 * should be turned into an object.
 *
 * @package Normalt
 */
class RecursiveReflectionNormalizer extends AggregateNormalizer implements AggregateNormalizerAware
{
    protected $normalizer;

    public function normalize($object, $format = null, array $context = array())
    {
        $normalized = array();
        $reflection = new ReflectionObject($object);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            $normalized[$property->getName()] = $this->normalizeValue($property->getValue($object));
        }

        return $normalized;
    }

    public function denormalize($data, $type, $format = null, array $context = array())
    {
        $prototype = $this->createPrototype($type);
        $reflection = new ReflectionObject($prototype);

        foreach ($reflection->getProperties() as $property) {
            if (false == isset($data[$property->getName()])) {
                continue;
            }

            $property->setAccessible(true);
            $property->setValue($prototype, $this->denormalizeValue($data[$property->getName()]));
        }

        return $prototype;
    }

    public function setAggregateNormalizer(AggregateNormalizer $aggregate)
    {
        $this->aggregate = $aggregate;
    }

    public function supportsNormalization($data, $format = null)
    {
        return is_object($data);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_array($data) && class_exists($type);
    }

    private function normalizeValue($data)
    {
        switch (true) {
            case is_scalar($data):
            case null === $data:
                return $data;

            case is_array($data):
                return $this->normalizeValues($data);

            case $normalizer = $this->getNormalizer($data):
                return $normalizer->normalize($data);

            default:
                return $this->aggregate->normalize($data);
        }
    }

    private function normalizeValues($data)
    {
        $normalized = array();

        foreach ($data as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function denormalizeValue($data)
    {
        switch (true) {
            case is_scalar($data):
                return $data;

            case $normalizer = $this->getDenormalizer($data, 'array'):
                return $normalizer->denormalize($data, 'array');

            case $this->aggregate->supportsDenormalization($data, 'array'):
                return $this->aggregate->denormalize($data, 'array');

            case is_array($data):
                return $this->denormalizeValues($data);
        }
    }

    private function denormalizeValues($data)
    {
        $denormalized = array();

        foreach ($data as $key => $value) {
            $denormalized[$key] = $this->denormalizeValue($value);
        }

        return $denormalized;
    }


    private function createPrototype($class)
    {
        return unserialize(sprintf('O:%u:"%s":0:{}', strlen($class), $class));
    }
}
