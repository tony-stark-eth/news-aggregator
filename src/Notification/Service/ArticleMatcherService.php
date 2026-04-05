<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Article\Entity\Article;
use App\Notification\Entity\AlertRule;
use App\Notification\Repository\AlertRuleRepositoryInterface;
use App\Notification\Repository\NotificationLogRepositoryInterface;
use App\Notification\ValueObject\MatchResult;
use App\Notification\ValueObject\MatchResultCollection;
use Psr\Clock\ClockInterface;

final readonly class ArticleMatcherService implements ArticleMatcherServiceInterface
{
    public function __construct(
        private AlertRuleRepositoryInterface $alertRuleRepository,
        private NotificationLogRepositoryInterface $notificationLogRepository,
        private ClockInterface $clock,
    ) {
    }

    public function match(Article $article): MatchResultCollection
    {
        /** @var list<AlertRule> $rules */
        $rules = $this->alertRuleRepository->findEnabled();

        $results = [];
        $articleCategory = $article->getCategory()?->getSlug();
        $searchText = mb_strtolower($article->getTitle() . ' ' . ($article->getContentText() ?? '') . ' ' . ($article->getSummary() ?? ''));

        foreach ($rules as $rule) {
            if (! $this->matchesCategory($rule, $articleCategory)) {
                continue;
            }

            if ($this->isInCooldown($rule)) {
                continue;
            }

            $matchedKeywords = $this->findMatchingKeywords($rule, $searchText);
            if ($matchedKeywords === []) {
                continue;
            }

            $results[] = new MatchResult($rule, $matchedKeywords);
        }

        return new MatchResultCollection($results);
    }

    private function matchesCategory(AlertRule $rule, ?string $articleCategory): bool
    {
        $categories = $rule->getCategories();
        if ($categories === []) {
            return true; // empty = match all categories
        }

        return $articleCategory !== null && in_array($articleCategory, $categories, true);
    }

    private function isInCooldown(AlertRule $rule): bool
    {
        $ruleId = $rule->getId();
        if ($ruleId === null) {
            return false;
        }

        $cooldownCutoff = $this->clock->now()->modify(sprintf('-%d minutes', $rule->getCooldownMinutes()));

        return $this->notificationLogRepository->existsRecentForRule($ruleId, $cooldownCutoff);
    }

    /**
     * @return list<string>
     */
    private function findMatchingKeywords(AlertRule $rule, string $searchText): array
    {
        $matched = [];
        foreach ($rule->getKeywords() as $keyword) {
            if (str_contains($searchText, mb_strtolower($keyword))) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }
}
