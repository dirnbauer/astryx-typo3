<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Components;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * The Astryx component collection.
 *
 * Templates reach it through the `a` prefix:
 *
 *     xmlns:a="http://typo3.org/ns/Webconsulting/AstryxTypo3/Components/ComponentCollection"
 *
 *     <a:layout.section>
 *       <a:layout.container>
 *         <a:atom.heading level="2">…</a:atom.heading>
 *       </a:layout.container>
 *     </a:layout.section>
 *
 * `a:atom.button` resolves to Atom/Button/Button.fluid.html, through Fluid's
 * own resolveTemplateName(): the dotted path is the directory, the last
 * fragment is both the directory and the file name.
 *
 * Four layers, in Brad Frost's order, and the dependency direction is enforced
 * by Tests/Unit/AtomicDesignConformanceTest.php rather than by convention:
 *
 *   Layout    the page's skeleton. Imports nothing.
 *   Atom      one indivisible thing. Imports nothing.
 *   Molecule  atoms in a named arrangement. May import Atom and Layout.
 *   Organism  a whole region of a page. May import anything.
 *
 * Every component renders exactly one root class, `astryx-<name>`, and carries
 * its modifiers as data attributes — `data-variant`, `data-size`, `data-level`
 * and the rest of the vocabulary in Build/Data/component-contract.json. That is
 * upstream's own contract since Astryx v0.6.0, where bare prop classes such as
 * `.primary` and `.level-2` were removed.
 */
final class ComponentCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([
            GeneralUtility::getFileAbsFileName(
                'EXT:astryx_typo3/Resources/Private/Components/',
            ),
        ]);

        return $templatePaths;
    }
}
