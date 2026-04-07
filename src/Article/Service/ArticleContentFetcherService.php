<?php

declare(strict_types=1);

namespace App\Article\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ArticleContentFetcherService implements ArticleContentFetcherServiceInterface
{
    private const string USER_AGENT = 'Mozilla/5.0 (compatible; NewsAggregator/1.0; +https://github.com/news-aggregator)';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(int:FULL_TEXT_FETCH_TIMEOUT)%')]
        private int $timeout,
    ) {
    }

    public function fetch(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => $this->timeout,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->logger->debug('Full-text fetch returned HTTP {status} for {url}', [
                'status' => $statusCode,
                'url' => $url,
            ]);

            throw new \RuntimeException(\sprintf('HTTP %d for %s', $statusCode, $url));
        }

        return $response->getContent();
    }
}
