<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Turn a video page URL into one a player can actually be framed with.
 *
 *     <g:embedUrl url="{f:uri.typolink(parameter: data.cta_link)}"/>
 *
 * An editor pastes the address from the browser bar — a YouTube *watch* URL, a
 * youtu.be short link, a Vimeo page. None of those may be put in an iframe:
 * YouTube and Vimeo both refuse to be framed on their watch pages, so the
 * element renders a permanently blank rectangle and the fault looks like ours.
 * The player lives at a different address, and this works out which one.
 *
 * YouTube is addressed through youtube-nocookie.com, which does not set its
 * tracking cookies until the visitor actually starts the film.
 *
 * Anything unrecognised is returned unchanged: a self-hosted MP4 or a
 * corporate video platform is passed straight through rather than mangled.
 */
final class EmbedUrlViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('url', 'string', 'The video URL as an editor entered it', true);
    }

    public function render(): string
    {
        $url = trim((string)($this->arguments['url'] ?? ''));
        if ($url === '' || $url === '#') {
            return '';
        }

        $id = self::youTubeId($url);
        if ($id !== null) {
            return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id);
        }

        $vimeo = self::vimeoId($url);
        if ($vimeo !== null) {
            return 'https://player.vimeo.com/video/' . rawurlencode($vimeo);
        }

        return $url;
    }

    /** The eleven-character id, from whichever shape of YouTube URL this is. */
    private static function youTubeId(string $url): ?string
    {
        $patterns = [
            '~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/watch\?(?:.*&)?v=([\w-]{11})~',
            '~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|v|shorts|live)/([\w-]{11})~',
            '~^https?://youtu\.be/([\w-]{11})~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private static function vimeoId(string $url): ?string
    {
        if (preg_match('~^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~', $url, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('~^https?://player\.vimeo\.com/video/(\d+)~', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
