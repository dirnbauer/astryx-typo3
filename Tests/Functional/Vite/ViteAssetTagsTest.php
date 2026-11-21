<?php

declare(strict_types=1);

namespace Webconsulting\AstryxTypo3\Tests\Functional\Vite;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * The <vite:asset> tags of the page layout and of the element preview, run
 * through praetorius/vite-asset-collector 2.x and the PageRenderer.
 *
 * The tags are read out of the real templates, so a tag added or changed
 * there is exercised here. The manifest is written into the test instance at
 * the collector's default location, keyed the way vite-plugin-typo3 3.x keys
 * entries: by the extension's unresolved, project-relative path (2.0 no
 * longer falls back to the symlink-resolved path). Main.entry.css references
 * woff2 files, as the real build does.
 *
 * The head must look as it did with 1.x: the theme stylesheet after the files
 * a site adds through page.includeCSS (the PageRenderer's addCssFile()), and
 * no font preloads. 2.x does the opposite by default.
 */
final class ViteAssetTagsTest extends FunctionalTestCase
{
    private const EXTENSION_ROOT = __DIR__ . '/../../..';

    private const ENTRIES = [
        'Main.entry.css' => 'assets/Main-astryx01.css',
        'Components.entry.js' => 'assets/Components-astryx01.js',
    ];

    private const FONTS = [
        'assets/figtree-latin-wght-normal-astryx01.woff2',
        'assets/figtree-latin-ext-wght-normal-astryx01.woff2',
    ];

    /** A stylesheet the way page.includeCSS adds it. */
    private const INCLUDE_CSS_PROBE = 'EXT:astryx_typo3/Resources/Public/Css/content-preview.css';

    // ext_emconf.php makes astryx_typo3 depend on desiderio, which depends on
    // content_blocks, form and workspaces.
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'form', 'workspaces'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'praetorius/vite-asset-collector',
        'webconsulting/desiderio',
        'webconsulting/astryx-typo3',
    ];

    protected bool $initializeDatabase = false;

    /** @return iterable<string, array{0: string}> */
    public static function templateProvider(): iterable
    {
        yield 'page layout' => ['Resources/Private/Templates/Layouts/Pages/Default.fluid.html'];
        yield 'element preview' => ['Resources/Private/Templates/Pages/ElementPreview.fluid.html'];
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function viteAssetTagsAddTheBuiltCssAndJavaScript(string $template): void
    {
        $this->writeManifest();
        $request = $this->createRequest();

        $pageRenderer = $this->get(PageRenderer::class);
        $pageRenderer->addCssFile(self::INCLUDE_CSS_PROBE);
        $this->renderViteTags($template, $request);

        $assetCollector = $this->get(AssetCollector::class);
        $styleSheets = $assetCollector->getStyleSheets();
        $javaScripts = array_column($assetCollector->getJavaScripts(), 'source');

        self::assertCount(1, $styleSheets, 'one stylesheet: ' . json_encode(array_keys($styleSheets)));
        $styleSheet = array_values($styleSheets)[0];
        self::assertStringEndsWith('_assets/vite/' . self::ENTRIES['Main.entry.css'], self::sourceString($styleSheet['source'] ?? null));
        self::assertNotTrue($styleSheet['options']['priority'] ?? false, 'the theme stylesheet is not added with priority');
        self::assertCount(1, $javaScripts, 'one script: ' . json_encode($javaScripts));
        self::assertStringEndsWith('_assets/vite/' . self::ENTRIES['Components.entry.js'], self::sourceString($javaScripts[0]));

        $html = $pageRenderer->render($request);

        $probe = strpos($html, 'content-preview.css');
        $main = strpos($html, self::ENTRIES['Main.entry.css']);
        self::assertNotFalse($probe, 'the includeCSS probe is rendered');
        self::assertNotFalse($main, 'the theme stylesheet is rendered');
        self::assertGreaterThan($probe, $main, 'Main.entry.css comes after page.includeCSS, as with 1.x');
        self::assertSame(0, substr_count($html, 'as="font"'), 'no font preloads');
        self::assertStringNotContainsString('.woff2', $html, 'no font file in the head');
        self::assertStringNotContainsString('rel="preload"', $html, 'no preload hints at all');
    }

    private function renderViteTags(string $template, ServerRequestInterface $request): void
    {
        $source = file_get_contents(self::EXTENSION_ROOT . '/' . $template);
        self::assertIsString($source, $template . ' is unreadable');
        preg_match_all('/<vite:asset\s[^>]*?(?:\/>|>.*?<\/vite:asset>)/s', $source, $matches);
        self::assertCount(2, $matches[0], $template . ' must embed the CSS and the JS entry');

        $context = $this->get(RenderingContextFactory::class)->create();
        $context->setAttribute(ServerRequestInterface::class, $request);
        $context->getTemplatePaths()->setTemplateSource(
            '<html xmlns:vite="http://typo3.org/ns/Praetorius/ViteAssetCollector/ViewHelpers" data-namespace-typo3-fluid="true">'
            . implode('', $matches[0])
            . '</html>'
        );
        (new TemplateView($context))->render();
    }

    private static function sourceString(mixed $source): string
    {
        self::assertTrue(is_string($source) || $source instanceof \Stringable);

        // 2.x prefixes asset sources with "URI:" on TYPO3 14.
        return (string)preg_replace('/^URI:/', '', (string)$source);
    }

    private function writeManifest(): void
    {
        $outputDir = Environment::getPublicPath() . '/_assets/vite';
        GeneralUtility::mkdir_deep($outputDir . '/.vite');
        GeneralUtility::mkdir_deep($outputDir . '/assets');

        $packageManager = $this->get(PackageManager::class);
        $projectPath = Environment::getProjectPath() . '/';
        $manifest = [];
        foreach (self::ENTRIES as $entry => $file) {
            $absolute = $packageManager->resolvePackagePath('EXT:astryx_typo3/Resources/Private/Assets/' . $entry);
            self::assertStringStartsWith($projectPath, $absolute, 'the extension must sit inside the test instance');
            $key = substr($absolute, strlen($projectPath));
            $manifest[$key] = ['file' => $file, 'src' => $key, 'isEntry' => true];
            if (str_ends_with($file, '.css')) {
                $manifest[$key]['assets'] = self::FONTS;
            }
            file_put_contents($outputDir . '/' . $file, str_ends_with($file, '.css') ? 'body{}' : 'export{};');
        }
        foreach (self::FONTS as $font) {
            file_put_contents($outputDir . '/' . $font, 'wOF2');
        }
        file_put_contents($outputDir . '/.vite/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function createRequest(): ServerRequestInterface
    {
        $request = new ServerRequest('https://astryx.example/', 'GET');

        return $request
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }
}
