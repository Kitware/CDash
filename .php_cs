// .php_cs
<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__);

$config = Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
        ->finder($finder)
            ->setUsingCache(true);

return $config;
