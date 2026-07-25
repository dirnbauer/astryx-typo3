<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Per-page theme override. Empty inherits from the parent page (levelfield
// slide in the body tag cObject) and finally from the desiderioGrande.theme
// site setting. Switching a theme is a repaint: the tokens are all runtime CSS
// custom properties, nothing is rebuilt and no content is touched.
//
// The field is registered globally (TCA is global), but hidden by TSconfig
// everywhere except on sites that load this theme's site set — see
// Configuration/page.tsconfig.
$themeItems = [
    [
        'label' => 'LLL:EXT:desiderio_grande/Resources/Private/Language/labels.xlf:pages.astryxTheme.inherit',
        'value' => '',
    ],
];

foreach ([
    'neutral' => 'Neutral — grayscale spine',
    'butter' => 'Butter — warm cream',
    'chocolate' => 'Chocolate — deep brown',
    'matcha' => 'Matcha — green',
    'stone' => 'Stone — cool grey',
    'gothic' => 'Gothic — high contrast',
    'y2k' => 'Y2K — saturated retro',
] as $value => $label) {
    $themeItems[] = ['label' => $label, 'value' => $value];
}

$GLOBALS['TCA']['pages']['columns']['tx_desideriogrande_theme'] = [
    'exclude' => true,
    'label' => 'LLL:EXT:desiderio_grande/Resources/Private/Language/labels.xlf:pages.astryxTheme',
    'description' => 'LLL:EXT:desiderio_grande/Resources/Private/Language/labels.xlf:pages.astryxTheme.description',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => $themeItems,
        'default' => '',
    ],
];

ExtensionManagementUtility::addFieldsToPalette(
    'pages',
    'layout',
    'tx_desideriogrande_theme',
    'after:backend_layout_next_level',
);
