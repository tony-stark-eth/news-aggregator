<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Service;

use App\Source\Service\FeedItem;
use App\Source\Service\LaminasFeedParserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaminasFeedParserService::class)]
#[CoversClass(FeedItem::class)]
final class LaminasFeedParserServiceTest extends TestCase
{
    private LaminasFeedParserService $parser;

    protected function setUp(): void
    {
        $this->parser = new LaminasFeedParserService();
    }

    public function testParseRss2Feed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test Feed</title>
    <link>https://example.com</link>
    <item>
      <title>Article One</title>
      <link>https://example.com/article-1</link>
      <description>&lt;p&gt;First article content&lt;/p&gt;</description>
      <pubDate>Fri, 04 Apr 2026 12:00:00 GMT</pubDate>
    </item>
    <item>
      <title>Article Two</title>
      <link>https://example.com/article-2</link>
      <description>Plain text content</description>
    </item>
  </channel>
</rss>
XML;

        $collection = $this->parser->parse($xml);
        $items = $collection->toArray();

        self::assertCount(2, $items);

        self::assertSame('Article One', $items[0]->title);
        self::assertSame('https://example.com/article-1', $items[0]->url);
        self::assertSame('<p>First article content</p>', $items[0]->contentRaw);
        self::assertSame('First article content', $items[0]->contentText);
        self::assertInstanceOf(\DateTimeImmutable::class, $items[0]->publishedAt);

        self::assertSame('Article Two', $items[1]->title);
        self::assertSame('https://example.com/article-2', $items[1]->url);
        self::assertSame('Plain text content', $items[1]->contentText);
    }

    public function testParseAtomFeed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Atom Feed</title>
  <link href="https://example.com"/>
  <entry>
    <title>Atom Article</title>
    <link href="https://example.com/atom-1"/>
    <content type="html">&lt;b&gt;Bold content&lt;/b&gt;</content>
    <updated>2026-04-04T12:00:00Z</updated>
  </entry>
</feed>
XML;

        $collection = $this->parser->parse($xml);
        $items = $collection->toArray();

        self::assertCount(1, $items);
        self::assertSame('Atom Article', $items[0]->title);
        self::assertSame('https://example.com/atom-1', $items[0]->url);
        self::assertSame('<b>Bold content</b>', $items[0]->contentRaw);
        self::assertSame('Bold content', $items[0]->contentText);
    }

    public function testSkipsItemsWithoutTitle(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <link>https://example.com</link>
    <item>
      <link>https://example.com/no-title</link>
      <description>No title here</description>
    </item>
  </channel>
</rss>
XML;

        $items = $this->parser->parse($xml);

        self::assertCount(0, $items);
    }

    public function testSkipsItemsWithoutLink(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <link>https://example.com</link>
    <item>
      <title>No Link Article</title>
      <description>Content without link</description>
    </item>
  </channel>
</rss>
XML;

        $items = $this->parser->parse($xml);

        self::assertCount(0, $items);
    }

    public function testHandlesHtmlEntitiesInContent(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <link>https://example.com</link>
    <item>
      <title>Entity Test</title>
      <link>https://example.com/entities</link>
      <description>&lt;p&gt;AT&amp;amp;T &amp;amp; other &amp;quot;entities&amp;quot;&lt;/p&gt;</description>
    </item>
  </channel>
</rss>
XML;

        $collection = $this->parser->parse($xml);
        $items = $collection->toArray();

        self::assertCount(1, $items);
        self::assertStringContainsString('AT&T', $items[0]->contentText ?? '');
    }

    public function testTrimOnStripHtmlKillsUnwrapTrim(): void
    {
        // After strip_tags + html_entity_decode + preg_replace, the text may have
        // leading/trailing single spaces (preg_replace collapses \s+ to single space).
        // Without trim: " Content here " (leading/trailing space from collapsed whitespace).
        // With trim: "Content here".
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <link>https://example.com</link>
    <item>
      <title>Trim Test</title>
      <link>https://example.com/trim</link>
      <description> &lt;p&gt; Trimmed content &lt;/p&gt; </description>
    </item>
  </channel>
</rss>
XML;

        $collection = $this->parser->parse($xml);
        $items = $collection->toArray();

        self::assertCount(1, $items);
        $contentText = $items[0]->contentText;
        self::assertNotNull($contentText);
        // With trim: no leading/trailing whitespace
        self::assertSame(trim($contentText), $contentText, 'Content text should not have leading/trailing whitespace');
        self::assertSame('Trimmed content', $contentText);
    }
}
