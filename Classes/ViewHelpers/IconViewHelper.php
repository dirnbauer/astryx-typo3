<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Webconsulting\Desiderio\Icon\IconRegistry;

/**
 * Draw one of Desiderio's semantic icons as an inline SVG.
 *
 *     <g:icon name="{item.icon}"/>
 *     <g:icon name="phone" class="g-thing__glyph"/>
 *
 * The icon *keys* come from Desiderio — the Select fields in this extension are
 * filled by its IconItemsProcessor, so the vocabulary has to be the same one.
 * The *markup* is ours, and deliberately not Desiderio's IconViewHelper: that
 * one emits a copy of the glyph for every supported library at once and relies
 * on Desiderio's stylesheet to hide the four that are not active. This theme
 * does not load that stylesheet, so the same call would stack five icons on top
 * of each other.
 *
 * Output is a single stroke SVG in currentColor with no size attributes beyond
 * the 24×24 default, so the surrounding component decides how big it is —
 * `.astryx-feature-icon > svg` already does exactly that.
 */
final class IconViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    /**
     * Which of Desiderio's five icon sets this theme draws.
     *
     * Lucide's even stroke weight and rounded caps are the closest match to
     * Astryx's own iconography. Changing this line changes every icon in the
     * theme; there is no per-site setting because a design system with two
     * icon vocabularies is not one design system.
     */
    private const LIBRARY = 'lucide';

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Semantic Desiderio icon key', true);
        $this->registerArgument('class', 'string', 'Additional CSS classes', false, '');
    }

    public function render(): string
    {
        $name = is_string($this->arguments['name'] ?? null) ? $this->arguments['name'] : '';
        if ($name === '') {
            return '';
        }

        $key = IconRegistry::normalizeKey($name);
        if ($key === 'none') {
            return '';
        }

        $paths = IconRegistry::paths($key, self::LIBRARY);
        if ($paths === '') {
            // An unknown key draws nothing rather than a placeholder glyph: the
            // icon is decoration here, and the label beside it already carries
            // the meaning.
            return '';
        }

        $class = is_string($this->arguments['class'] ?? null) ? $this->arguments['class'] : '';
        $classAttribute = trim('g-icon ' . $class);

        return sprintf(
            '<svg class="%s" data-icon-name="%s" xmlns="http://www.w3.org/2000/svg" '
            . 'width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true" focusable="false">%s</svg>',
            htmlspecialchars($classAttribute, ENT_QUOTES | ENT_HTML5),
            htmlspecialchars($key, ENT_QUOTES | ENT_HTML5),
            $paths,
        );
    }
}
