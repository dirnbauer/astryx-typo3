<?php

declare(strict_types=1);

/*
 * PHPUnit bootstrap for the functional test suite.
 *
 * A copy of typo3/testing-framework's own
 * Resources/Core/Build/FunctionalTestsBootstrap.php, which the framework asks
 * extensions to copy rather than include from vendor/.
 *
 * It only defines ORIGINAL_ROOT (the repository's Composer web-dir, `public/`,
 * exported as TYPO3_PATH_ROOT by typo3/cms-composer-installers) and creates the
 * two directories the per-test instances are built in. Everything else happens
 * per test case inside FunctionalTestCase::setUp().
 */
(static function (): void {
    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();
