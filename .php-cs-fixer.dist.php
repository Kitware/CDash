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
    ->in(__DIR__);

$config = new Config();
return $config->setRules([
    '@PSR12' => true,
    '@PSR12:risky' => true,
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
    // The following rules are a subset of @Symfony:risky and should eventually be replaced by the full ruleset.
    'array_push' => true,
    'combine_nested_dirname' => true,
    'dir_constant' => true,
    'ereg_to_preg' => true,
    'fopen_flag_order' => true,
    'function_to_constant' => true,
    'get_class_to_class_keyword' => true,
    'implode_call' => true,
    'is_null' => true,
    'logical_operators' => true,
    'long_to_shorthand_operator' => true,
    'modernize_strpos' => ['modernize_stripos' => true],
    'modernize_types_casting' => true,
    'no_homoglyph_names' => true,
    'no_useless_sprintf' => true,
    'non_printable_character' => true,
    'ordered_traits' => true,
])->setFinder($finder);
