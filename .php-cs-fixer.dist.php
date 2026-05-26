<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'types_spaces' => ['space' => 'none'],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_to_comment' => false,
        'fopen_flags' => ['b_mode' => true],
        'native_function_invocation' => ['include' => ['@all']],
        'function_declaration' => ['closure_function_spacing' => 'none', 'closure_fn_spacing' => 'none'],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
    ])
;
