<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/public');

$finder->append([__DIR__ . 'routes.php']);

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,

    'declare_strict_types' => true,

    'single_quote' => true,

    'no_unused_imports' => true,

    'ordered_imports' => ['sort_algorithm' => 'alpha'],

    'no_closing_tag' => true,

    'braces' => [
        'allow_single_line_closure'                   => true,
        'position_after_functions_and_oop_constructs' => 'same',
        'position_after_control_structures'           => 'same',
    ],
    'elseif' => true,

    'function_declaration' => [
        'closure_function_spacing' => 'one',
    ],

    'method_argument_space' => [
        'on_multiline'                     => 'ensure_fully_multiline',
        'keep_multiple_spaces_after_comma' => false,
    ],

    'blank_line_after_namespace'   => true,
    'blank_line_after_opening_tag' => true,

    'no_multiple_statements_per_line' => true,

    'no_extra_blank_lines' => [
        'tokens' => [
            'extra',
            'use',
            'use_trait',
            'curly_brace_block',
            'parenthesis_brace_block',
            'square_brace_block',
            'throw',
        ],
    ],

    'indentation_type' => true, // (covered by PSR12)

    'no_trailing_whitespace'      => true,
    'no_whitespace_in_blank_line' => true,

    'class_definition' => [
        'single_line'                         => true,
        'single_item_single_line'             => true,
        'multi_line_extends_each_single_line' => true,
    ],

    'visibility_required' => [
        'elements' => ['property', 'method', 'const'],
    ],

    'concat_space' => [
        'spacing' => 'one',
    ],

    'simple_to_complex_string_variable' => true,

    'cast_spaces' => [
        'space' => 'single',
    ],

    'phpdoc_align'            => ['align' => 'vertical'],
    'phpdoc_scalar'           => true,
    'phpdoc_summary'          => true,
    'phpdoc_trim'             => true,
    'phpdoc_to_comment'       => false,
    'phpdoc_var_without_name' => true,

    'native_function_casing' => true,
    'constant_case'          => ['case' => 'lower'],
    'magic_method_casing'    => true,
    'magic_constant_casing'  => true,

    'no_trailing_comma_in_singleline_array' => true,
    'trailing_comma_in_multiline'           => [
        'elements' => ['arrays', 'arguments', 'parameters'],
    ],

    'binary_operator_spaces' => [
        'default'   => 'single_space',
        'operators' => ['=>' => 'align_single_space_minimal'],
    ],

    'single_blank_line_at_eof' => true,
])
    ->setFinder($finder)
    ->setIndent('    ') // 4 spaces
    ->setLineEnding("\n");
