<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A multi-step Powermail form sends from its last page only.
 *
 * Powermail renders every submit field an editor adds, on whatever page it
 * sits. In a multi-step form the step buttons move between pages, so a submit
 * on step 1 posted the whole form from there, next to the "Next" button that
 * was meant to be pressed. The page partial therefore keeps every submit field
 * out of the field list and renders only the last page's, in the step row.
 */
final class PowermailMultiStepTest extends TestCase
{
    public function testNoPageRendersASubmitFieldAmongItsFieldsInAMultiStepForm(): void
    {
        $page = (string)file_get_contents(dirname(__DIR__, 2) . '/Resources/Private/Extensions/Powermail/Partials/Form/Page.html');

        self::assertStringContainsString("<f:if condition=\"{settings.main.moresteps} && {field.type} == 'submit'\">", $page);
        self::assertSame(1, substr_count($page, '<f:render partial="Form/Field/'), 'the field list has one way to render a field');
        self::assertStringContainsString('<f:if condition="{iterationPages.isLast}">', $page);
        self::assertSame(1, substr_count($page, 'type="submit"'), 'one submit button: the last page\'s, in the step row');
    }
}
