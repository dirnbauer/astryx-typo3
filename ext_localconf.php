<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Join Desiderio's element library catalog. The picker then lists this
// extension's Content Blocks with their previews, keyword chips and
// "when to use" descriptions, exactly like the elements Desiderio ships.
// Sites choose which providers they offer through the elementLibrary.hosts
// site setting; see Desiderio's Documentation/Developer/Index.rst.
$hosts = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['desiderio']['libraryHostExtensions'] ?? [];
$hosts = is_array($hosts) ? $hosts : [];
$hosts[] = 'desiderio_grande';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['desiderio']['libraryHostExtensions'] = array_values(array_unique($hosts));

// This extension's shared RecordTypes. Desiderio's seeders resolve a
// Collection's `foreign_table:` to the record type it points at; without this
// they see an empty field list and write child rows whose file fields stay 0 —
// which is why every portrait and logo inside a shared collection was blank.
// NOT __DIR__: TYPO3 concatenates every ext_localconf.php into one cached file
// under var/cache/code/core/, and __DIR__ inside it resolves to that cache
// directory rather than to this extension. extPath() is the only thing that
// survives the concatenation.
$paths = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['desiderio']['recordTypePaths'] ?? [];
$paths = is_array($paths) ? $paths : [];
$paths[] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('desiderio_grande') . 'ContentBlocks/RecordTypes';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['desiderio']['recordTypePaths'] = array_values(array_unique($paths));

// The g: namespace, so element templates can call <g:icon name="…"/> without
// each of them declaring an xmlns.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['g'][] = 'Webconsulting\\DesiderioGrande\\ViewHelpers';
