<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class LayerDependencyTest
{
    public function testSharedDoesNotDependOnDomains(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Shared'))
            ->excluding(
                Selector::inNamespace('App\Shared\Controller'),
                Selector::inNamespace('App\Shared\Search'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Article'),
                Selector::inNamespace('App\Source'),
                Selector::inNamespace('App\Enrichment'),
                Selector::inNamespace('App\Notification'),
                Selector::inNamespace('App\Digest'),
                Selector::inNamespace('App\User'),
            );
    }

    public function testEntitiesDoNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::classname('/.*\\\\Entity\\\\.*/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/.*\\\\Controller\\\\.*/', true));
    }

    public function testSourceDoesNotDependOnOtherDomains(): Rule
    {
        // SeedDataCommand seeds data across all domains (sources + digests).
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Source'))
            ->excluding(Selector::classname('App\Source\Command\SeedDataCommand'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Article'),
                Selector::inNamespace('App\Enrichment'),
                Selector::inNamespace('App\Notification'),
                Selector::inNamespace('App\Digest'),
            );
    }

    public function testArticleDoesNotDependOnEnrichmentOrNotification(): Rule
    {
        // FetchSourceHandler and EnrichArticleHandler are orchestration pipelines —
        // they legitimately coordinate enrichment after persisting/loading articles.
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Article'))
            ->excluding(
                Selector::classname('App\Article\MessageHandler\FetchSourceHandler'),
                Selector::classname('App\Article\MessageHandler\EnrichArticleHandler'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Enrichment'),
                Selector::inNamespace('App\Notification'),
                Selector::inNamespace('App\Digest'),
            );
    }

    public function testEnrichmentDoesNotDependOnNotificationOrDigest(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Enrichment'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Notification'),
                Selector::inNamespace('App\Digest'),
            );
    }

    public function testChatDoesNotDependOnNonSharedDomains(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Chat'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('App\Enrichment'),
                Selector::inNamespace('App\Notification'),
                Selector::inNamespace('App\Digest'),
                Selector::inNamespace('App\Source'),
                Selector::inNamespace('App\User'),
            );
    }
}
