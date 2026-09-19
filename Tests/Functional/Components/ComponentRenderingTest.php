<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Functional\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Every Fluid component renders, and renders its own root class.
 *
 * The component list is globbed at runtime rather than written down here: the
 * 2.0 component layer is still being authored, and a test that has to be
 * edited whenever a component lands is a test that will be edited to stay
 * green. Everything on disk is rendered; everything on disk must therefore
 * carry a row in Build/Data/component-contract.json, which is the file the CSS
 * build, the template refactoring script and the backend all read.
 *
 * Fluid 5 validates component arguments at parse time, so a renamed or newly
 * required <f:argument> fails here rather than on a live page. Required
 * arguments are read straight out of the component's own declarations and
 * filled with the smallest legal value for their type — nothing is skipped,
 * and nothing is hard-coded per component.
 */
final class ComponentRenderingTest extends FunctionalTestCase
{
    private const COMPONENT_ROOT = __DIR__ . '/../../../Resources/Private/Components';

    private const CONTRACT = __DIR__ . '/../../../Build/Data/component-contract.json';
    // Not a wish list: ext_emconf.php makes astryx_typo3 depend on desiderio,
    // which in turn depends on content_blocks and the workspaces system
    // extension, and the testing framework refuses to build an instance whose
    // declared dependencies are absent.
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'form', 'workspaces'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'webconsulting/desiderio',
        'webconsulting/astryx-typo3',
    ];

    /**
     * Data providers run before TYPO3 is bootstrapped, so this reads the
     * directory layout the collection maps — Layer/Name/Name.fluid.html, which
     * ComponentCollection resolves as the Fluid component "layer.name".
     *
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function componentProvider(): iterable
    {
        $contract = self::contract();

        foreach (self::componentsOnDisk() as $component => $file) {
            if (!isset($contract[$component]['rootClass']) || !is_string($contract[$component]['rootClass'])) {
                // Deliberately not a skip: a component without a contract row
                // is invisible to the CSS build and to the backend.
                self::fail(sprintf(
                    '%s has no "rootClass" in Build/Data/component-contract.json (component "%s").',
                    $file,
                    $component,
                ));
            }

            yield $component => [$component, $file, $contract[$component]['rootClass']];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function contract(): array
    {
        $raw = file_get_contents(self::CONTRACT);
        self::assertIsString($raw, self::CONTRACT . ' is unreadable');
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('components', $decoded);
        self::assertIsArray($decoded['components']);

        /** @var array<string, array<string, mixed>> $components */
        $components = $decoded['components'];

        return $components;
    }

    /**
     * @return array<string, string> component name => absolute template path
     */
    private static function componentsOnDisk(): array
    {
        $files = glob(self::COMPONENT_ROOT . '/*/*/*.fluid.html');
        self::assertIsArray($files, 'globbing the component directory failed');

        $components = [];
        foreach ($files as $file) {
            $name = basename($file, '.fluid.html');
            $directory = dirname($file);
            if (basename($directory) !== $name) {
                self::fail(sprintf(
                    '%s must live in a directory of its own name (Layer/%s/%s.fluid.html).',
                    $file,
                    $name,
                    $name,
                ));
            }
            $components[lcfirst(basename(dirname($directory))) . '.' . lcfirst($name)] = $file;
        }
        ksort($components);

        return $components;
    }

    #[Test]
    #[DataProvider('componentProvider')]
    public function componentRendersItsRootClass(string $component, string $file, string $rootClass): void
    {
        $source = (string)file_get_contents($file);
        $arguments = self::synthesiseRequiredArguments($source);

        $renderingContext = $this->get(RenderingContextFactory::class)->create([], $this->frontendRequest());
        $view = new TemplateView($renderingContext);
        $view->getRenderingContext()->getTemplatePaths()->setTemplateSource(
            self::callSite($component, array_keys($arguments)),
        );
        $view->assignMultiple($arguments);

        $rendered = $view->render();

        self::assertNotSame('', trim($rendered), $component . ' rendered nothing');
        self::assertStringContainsString(
            $rootClass,
            $rendered,
            sprintf(
                '%s must render its contract root class "%s". Rendered: %s',
                $component,
                $rootClass,
                $rendered,
            ),
        );
    }

    /**
     * Reads the component's own <f:argument> declarations and produces the
     * smallest legal value for every argument that is not optional.
     *
     * @return array<string, mixed>
     */
    private static function synthesiseRequiredArguments(string $source): array
    {
        $matches = [];
        preg_match_all('#<f:argument\b([^>]*?)/?>#s', $source, $matches, PREG_SET_ORDER);

        $arguments = [];
        foreach ($matches as $match) {
            $attributes = self::attributes($match[1]);
            $name = $attributes['name'] ?? '';
            if ($name === '') {
                continue;
            }
            $optional = strtolower(trim($attributes['optional'] ?? ''));
            if (in_array($optional, ['{true}', 'true', '1'], true)) {
                continue;
            }
            $arguments[$name] = self::valueForType(strtolower(trim($attributes['type'] ?? 'string')));
        }

        return $arguments;
    }

    /**
     * @return array<string, string>
     */
    private static function attributes(string $attributeString): array
    {
        $matches = [];
        preg_match_all('#([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"#', $attributeString, $matches, PREG_SET_ORDER);

        $attributes = [];
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        return $attributes;
    }

    private static function valueForType(string $type): mixed
    {
        return match ($type) {
            'bool', 'boolean' => false,
            'int', 'integer' => 1,
            'float', 'double' => 1.0,
            'array' => [],
            default => 'x',
        };
    }

    /**
     * Builds a template that calls the component. Arguments travel as template
     * variables so that arrays and booleans survive as their own type instead
     * of being flattened into a string attribute.
     *
     * @param list<string> $argumentNames
     */
    private static function callSite(string $component, array $argumentNames): string
    {
        $attributes = '';
        foreach ($argumentNames as $name) {
            $attributes .= sprintf(' %s="{%s}"', $name, $name);
        }

        return sprintf(
            '<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"'
            . ' xmlns:a="http://typo3.org/ns/Webconsulting/AstryxTypo3/Components/ComponentCollection"'
            . ' data-namespace-typo3-fluid="true">'
            . '<a:%1$s%2$s>component slot</a:%1$s>'
            . '</html>',
            $component,
            $attributes,
        );
    }

    /**
     * The link and translate ViewHelpers a component may reach for need a
     * frontend request carrying a site and a language; this is the smallest one
     * that satisfies them.
     */
    private function frontendRequest(): ServerRequest
    {
        $site = new Site('astryx', 1, [
            'base' => 'https://example.com/',
            'websiteTitle' => 'Astryx',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);

        return (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }
}
