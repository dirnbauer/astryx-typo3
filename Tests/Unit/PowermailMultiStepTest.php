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
        // powermail_cond hides a field by its wrapper; without one a condition cannot hide the button.
        self::assertStringContainsString('<span class="powermail_fieldwrap powermail_fieldwrap_{field.marker}">', $page);
    }

    public function testTheConditionsStartingStateRendersOnlyWherePowermailCondIsActive(): void
    {
        $form = (string)file_get_contents(dirname(__DIR__, 2) . '/Resources/Private/Extensions/Powermail/Templates/Form/Form.html');
        $state = (string)file_get_contents(dirname(__DIR__, 2) . '/Resources/Private/Extensions/Powermail/Partials/Misc/ConditionsState.html');

        // The partial's pc: namespace exists only with powermail_cond, which sets the switch.
        self::assertStringContainsString('<f:if condition="{settings.powermailCond.prerender}">', $form);
        self::assertStringContainsString('<f:render partial="Misc/ConditionsState" arguments="{form: form}" />', $form);
        self::assertStringContainsString('id="form-{form.uid}-actions"', $state);
        self::assertStringContainsString('{pc:conditions(form: form) -> f:format.raw()}', $state);
    }
}
