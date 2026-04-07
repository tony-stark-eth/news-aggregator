<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Source\Service\FeedLanguageDetectorService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedLanguageDetectorService::class)]
final class FeedLanguageDetectorServiceTest extends TestCase
{
    private FeedLanguageDetectorService $detector;

    protected function setUp(): void
    {
        $this->detector = new FeedLanguageDetectorService();
    }

    public function testDetectRssLanguageElement(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <language>en-US</language>
                    <item>
                        <title>Hello World</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('en', $this->detector->detect($xml));
    }

    public function testDetectRssLanguageGerman(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Deutsches Feed</title>
                    <language>de-DE</language>
                    <item>
                        <title>Hallo Welt</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('de', $this->detector->detect($xml));
    }

    public function testDetectAtomXmlLangAttribute(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom" xml:lang="fr">
                <title>Feed Francais</title>
                <entry>
                    <title>Bonjour le monde</title>
                    <link href="https://example.com/1" />
                </entry>
            </feed>
            XML;

        self::assertSame('fr', $this->detector->detect($xml));
    }

    public function testDetectDcLanguageInRssItem(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
                <channel>
                    <title>Test Feed</title>
                    <item>
                        <title>Hola mundo</title>
                        <link>https://example.com/1</link>
                        <dc:language>es</dc:language>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('es', $this->detector->detect($xml));
    }

    public function testDetectGermanCharactersInTitle(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <item>
                        <title>Nachrichten über Deutschland</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('de', $this->detector->detect($xml));
    }

    public function testDetectFrenchCharactersInTitle(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <item>
                        <title>Les dernières nouvelles de la journée</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('fr', $this->detector->detect($xml));
    }

    public function testDetectSpanishCharactersInTitle(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <item>
                        <title>El niño juega en la plaza</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('es', $this->detector->detect($xml));
    }

    public function testReturnsNullForEnglishWithNoLanguageTag(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <item>
                        <title>Hello World</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertNull($this->detector->detect($xml));
    }

    public function testReturnsNullForInvalidXml(): void
    {
        self::assertNull($this->detector->detect('not valid xml at all'));
    }

    public function testNormalizesLanguageCodeWithRegion(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <language>pt-BR</language>
                    <item>
                        <title>Ola mundo</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('pt', $this->detector->detect($xml));
    }

    public function testMultibyteLanguageCodeNormalization(): void
    {
        // Ensure mb_strtolower is correctly used for uppercase codes
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <language>DE</language>
                    <item>
                        <title>Test</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('de', $this->detector->detect($xml));
    }

    public function testRssLanguagePriorityOverCharacterHeuristic(): void
    {
        // RSS <language> says English, but title has German chars
        // XML tag should take priority
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <language>en</language>
                    <item>
                        <title>Nachrichten über Deutschland</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('en', $this->detector->detect($xml));
    }

    public function testEmptyFeedReturnsNull(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Empty Feed</title>
                </channel>
            </rss>
            XML;

        self::assertNull($this->detector->detect($xml));
    }

    public function testDcLanguageInAtomEntry(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
                <title>Test Feed</title>
                <entry>
                    <title>Test Entry</title>
                    <link href="https://example.com/1" />
                    <dc:language>it</dc:language>
                </entry>
            </feed>
            XML;

        self::assertSame('it', $this->detector->detect($xml));
    }

    public function testLanguageWithWhitespaceTrimmed(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Test Feed</title>
                    <language>  en  </language>
                    <item>
                        <title>Test</title>
                        <link>https://example.com/1</link>
                    </item>
                </channel>
            </rss>
            XML;

        self::assertSame('en', $this->detector->detect($xml));
    }
}
