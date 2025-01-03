<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('app/cdash/config')
    ->exclude('bootstrap/cache')
    ->exclude('node_modules')
    ->exclude('storage')
    ->exclude('vendor')
    ->exclude('_build')
    ->exclude('resources')
    ->notPath('app/cdash/tests/config.test.local.php')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
