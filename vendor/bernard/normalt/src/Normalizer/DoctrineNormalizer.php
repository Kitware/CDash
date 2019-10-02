<?php

namespace Normalt\Normalizer;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Normalize and Denormalize any entities that doctrine have metadata for.
 * The normalized form will be ['className' => 'My\\Entity', 1, 2, 3] where the
 * array (className substracted) will be used as the identifier values when
 * refinding the object.
 *
 * @author Jonathan Wage
 * @package Normalt
 */
class DoctrineNormalizer implements NormalizerInterface, DenormalizerInterface
{
    protected $objectManager;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $class = $this->objectManager->getClassMetadata(get_class($object));

        return array('className' => $class->getName()) + $class->getIdentifierValues($object);
    }

    public function denormalize($data, $type, $format = null, array $context = array())
    {
        $className = $data['className'];

        unset($data['className']);

        return $this->objectManager->find($className, $data);
    }

    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && $this->hasMetadataFor(get_class($data));
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return isset($data['className']) && $this->hasMetadataFor($data['className']);
    }

    private function hasMetadataFor($class)
    {
        try {
            $this->objectManager->getClassMetadata($class);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
