<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Astryx for TYPO3 sorts its elements into the same ten wizard groups Desiderio
// uses, so an editor working on either theme reads the same shelf labels and the
// element library shows one set of category chips.
//
// Desiderio always loads first (it is a hard dependency) and registers these
// groups with its own labels; addTcaSelectItemGroup() keeps the first
// registration, so the loop below is inert while Desiderio is installed. It is
// here so this extension's groups still exist — and stay in this order — if it
// is ever used without it.
$position = 'before:default';

foreach ([
    'content' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.content',
    'conversion' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.conversion',
    'data' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.data',
    'features' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.features',
    'footer' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.footer',
    'hero' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.hero',
    'navigation' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.navigation',
    'pricing' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.pricing',
    'social-proof' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.socialProof',
    'team' => 'LLL:EXT:astryx_typo3/Resources/Private/Language/labels.xlf:contentElementGroup.team',
] as $group => $label) {
    ExtensionManagementUtility::addTcaSelectItemGroup(
        'tt_content',
        'CType',
        $group,
        $label,
        $position,
    );
    $position = 'after:' . $group;
}
