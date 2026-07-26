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

// The g: namespace, so element templates can call <g:icon name="…"/> without
// each of them declaring an xmlns.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['g'][] = 'Webconsulting\\DesiderioGrande\\ViewHelpers';
