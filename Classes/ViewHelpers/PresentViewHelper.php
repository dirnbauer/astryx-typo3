<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Keep only the entries of an attribute array that have a value.
 *
 *     <f:link.typolink parameter="{parameter}"
 *         additionalAttributes="{g:present(of: {'aria-label': ariaLabel, 'tabindex': tabindex})}">
 *
 * Tag-building ViewHelpers print an attribute whose value is an empty string,
 * so `additionalAttributes` with optional component arguments left
 * `aria-label=""`, `tabindex=""` and the like on every link. Null, false and
 * blank strings are dropped; everything else is passed on unchanged.
 */
final class PresentViewHelper extends AbstractViewHelper
{
    /** It returns an array; escaping would turn it into a string. */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('of', 'array', 'Attribute name => value', true);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function render(): array
    {
        $attributes = $this->arguments['of'];
        if (!is_array($attributes)) {
            return [];
        }

        return array_filter(
            $attributes,
            static fn(mixed $value): bool => $value !== null && $value !== false && !(is_string($value) && trim($value) === '')
        );
    }
}
