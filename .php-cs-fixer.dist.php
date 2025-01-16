<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->exclude('app/cdash/config')
    ->exclude('bootstrap/cache')
    ->exclude('node_modules')
    ->exclude('storage')
    ->exclude('vendor')
    ->exclude('_build')
    ->exclude('resources')
    ->exclude('public')
    ->exclude('app/cdash/tests/kwtest/simpletest')
    ->notPath('app/cdash/tests/config.test.local.php')
    ->in(__DIR__);

$config = new Config();
return $config->setRules([
    '@PSR12' => true,
    '@PHP82Migration' => true,
    '@PHP82Migration:risky' => true,
    '@Symfony' => true,
    'yoda_style' => false,
    'blank_line_before_statement' => false,
    'phpdoc_summary' => false,
    'concat_space' => ['spacing' => 'one'],
    'increment_style' => ['style' => 'post'],
    'fully_qualified_strict_types' => ['import_symbols' => true],
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => null, 'import_functions' => null],
    'phpdoc_align' => ['align' => 'left'],
    'declare_strict_types' => false, // TODO: turn this back on.  Currently causes errors...
    'void_return' => false, // TODO: turn this back on.  Currently causes errors...
])->setFinder($finder);
