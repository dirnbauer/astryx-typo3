<?php

declare(strict_types=1);

namespace Webconsulting\DesiderioGrande\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\DesiderioGrande\ViewHelpers\EmbedUrlViewHelper;

/**
 * The URLs an editor actually pastes, and what a player can be framed with.
 *
 * This existed because every video element dropped the link straight into an
 * iframe, and a YouTube watch page refuses to be framed — so the element
 * rendered a blank rectangle and the fault looked like ours.
 */
final class EmbedUrlViewHelperTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function urls(): array
    {
        return [
            'watch page' => [
                'https://www.youtube.com/watch?v=xacNOQeUHpI',
                'https://www.youtube-nocookie.com/embed/xacNOQeUHpI',
            ],
            'watch page with a start time' => [
                'https://www.youtube.com/watch?v=xacNOQeUHpI&t=42s',
                'https://www.youtube-nocookie.com/embed/xacNOQeUHpI',
            ],
            'watch page with the id last' => [
                'https://www.youtube.com/watch?list=PLabc&v=xacNOQeUHpI',
                'https://www.youtube-nocookie.com/embed/xacNOQeUHpI',
            ],
            'short link' => [
                'https://youtu.be/xacNOQeUHpI',
                'https://www.youtube-nocookie.com/embed/xacNOQeUHpI',
            ],
            'shorts' => [
                'https://www.youtube.com/shorts/xacNOQeUHpI',
                'https://www.youtube-nocookie.com/embed/xacNOQeUHpI',
            ],
            'already an embed' => [
                'https://www.youtube.com/embed/xacNOQeUHpI',
                'https://www.youtube-nocookie.com/embed/xacNOQeUHpI',
            ],
            'vimeo page' => [
                'https://vimeo.com/76979871',
                'https://player.vimeo.com/video/76979871',
            ],
            // Anything we do not recognise is passed through untouched, so a
            // self-hosted file or a corporate platform still works.
            'self-hosted file' => [
                'https://example.org/media/demo.mp4',
                'https://example.org/media/demo.mp4',
            ],
            'unseeded placeholder' => ['#', ''],
            'empty' => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function itResolvesToAFramableUrl(string $input, string $expected): void
    {
        $viewHelper = new EmbedUrlViewHelper();
        $viewHelper->setArguments(['url' => $input]);

        self::assertSame($expected, $viewHelper->render());
    }
}
