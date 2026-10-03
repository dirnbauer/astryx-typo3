<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Print one HTML attribute, or nothing when it has no value.
 *
 *     <button type="button"{g:attribute(name: 'aria-label', value: ariaLabel)}>
 *
 * A plain `aria-label="{ariaLabel}"` in a template prints the attribute even
 * when the argument is empty, and an empty tabindex, aria-hidden or
 * aria-current is invalid HTML while an empty aria-label or id only adds
 * noise. Components write optional attributes through this helper instead.
 * The output starts with a space when there is something to print, so it
 * sits directly after the previous attribute. The value is escaped here.
 */
final class AttributeViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Attribute name', true);
        $this->registerArgument('value', 'mixed', 'Attribute value; null, false and empty strings print nothing', false);
    }

    public function render(): string
    {
        $name = (string)$this->arguments['name'];
        $value = $this->arguments['value'] ?? null;
        if (preg_match('/^[A-Za-z_:][-A-Za-z0-9_:.]*$/', $name) !== 1) {
            return '';
        }
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            $value = $name;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }
        $value = (string)$value;
        if (trim($value) === '') {
            return '';
        }

        return ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5) . '"';
    }
}
