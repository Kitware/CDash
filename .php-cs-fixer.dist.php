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
        '@PSR2' => true,
        '@PHP70Migration' => true,
        'method_argument_space' => ['on_multiline' => 'ignore'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder)
;
