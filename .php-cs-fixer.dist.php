<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tests'])
;

$config = new PhpCsFixer\Config();
return $config
    // keep close to the Symfony standard but allow
    // alignment of keys/values in array definitions
    ->setRules([
        '@Symfony'               => true,
        'binary_operator_spaces' => false,
    ])
    ->setFinder($finder)
;
