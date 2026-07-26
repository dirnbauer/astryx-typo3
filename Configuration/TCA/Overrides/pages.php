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
    // Astryx's own seven.
    'neutral' => 'Neutral — a pure grayscale spine',
    'butter' => 'Butter — cream paper with one vivid',
    'chocolate' => 'Chocolate — warm browns with a serif',
    'matcha' => 'Matcha — deep green on paper, headings',
    'stone' => 'Stone — cool grey with montserrat headings',
    'gothic' => 'Gothic — dark even in light mode',
    'y2k' => 'Y2K — lavender canvas and square corners',
    // This extension's eighteen, built on the same token contract.
    'harbour' => 'Harbour — deep maritime navy with a',
    'ember' => 'Ember — warm charcoal lit by a',
    'linen' => 'Linen — a soft warm neutral built',
    'orchid' => 'Orchid — muted purple with a cool',
    'cobalt' => 'Cobalt — a saturated engineering blue on',
    'moss' => 'Moss — deep forest green, quieter and',
    'clay' => 'Clay — terracotta and warm stone',
    'plum' => 'Plum — deep aubergine on a faintly',
    'sand' => 'Sand — desert warmth',
    'ink' => 'Ink — near-black on white at the',
    'lagoon' => 'Lagoon — blue-green water on pale sand',
    'rose' => 'Rose — dusty rose, deliberately desaturated so',
    'graphite' => 'Graphite — cool monochrome with no hue',
    // Five adapted from well-known open-source palettes; see Build/Data/grande-themes.json.
    'frost' => 'Frost — arctic blue-grey, desaturated to the',
    'latte' => 'Latte — pastel mauve on a cool',
    'solar' => 'Solar — cream paper and dark cyan',
    'retro' => 'Retro — warm amber on aged cream',
    'midnight' => 'Midnight — indigo on a faintly blue',
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
