<?php

namespace Normalt\Normalizer;

interface AggregateNormalizerAware
{
    function setAggregateNormalizer(AggregateNormalizer $aggregate);
}
