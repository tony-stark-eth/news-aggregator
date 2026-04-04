<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Exception\FeedFetchException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpFeedFetcherService implements FeedFetcherServiceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(string $feedUrl): string
    {
        try {
            $response = $this->httpClient->request('GET', $feedUrl, [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml',
                    'User-Agent' => 'NewsAggregator/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw FeedFetchException::fromUrl($feedUrl, sprintf('HTTP %d', $statusCode));
            }

            return $response->getContent();
        } catch (FeedFetchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw FeedFetchException::fromUrl($feedUrl, $e->getMessage(), $e);
        }
    }
}
