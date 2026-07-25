<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Desiderio Grande',
    'description' => 'The Astryx design system, server-rendered for TYPO3 14: 250 Content Blocks across ten categories, seven runtime-switchable themes with dark mode, and a page shell — Fluid and CSS, no JavaScript framework.',
    'category' => 'templates',
    'author' => 'webconsulting studio',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'content_blocks' => '2.2.0-2.99.99',
            'desiderio' => '3.2.0-3.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
