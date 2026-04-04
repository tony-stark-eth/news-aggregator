<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Article\Entity\Article;
use App\Article\Service\ScoringServiceInterface;
use App\Enrichment\Service\CategorizationServiceInterface;
use App\Enrichment\Service\SummarizationServiceInterface;
use App\Notification\Entity\AlertRule;
use App\Notification\Service\ArticleMatcherServiceInterface;
use App\Notification\ValueObject\AlertRuleType;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversNothing]
final class GoldenPathTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testFullPipeline(): void
    {
        // Seed
        $category = new Category('Tech', 'tech-gp', 10, '#3B82F6');
        $this->em->persist($category);
        $source = new Source('GP Source', 'https://example.com/gp', $category, new \DateTimeImmutable());
        $this->em->persist($source);
        $user = new User('gp@test.com', 'hashed');
        $this->em->persist($user);
        $rule = new AlertRule('Tech Alert', AlertRuleType::Keyword, $user, new \DateTimeImmutable());
        $rule->setKeywords(['software', 'technology']);
        $this->em->persist($rule);
        $this->em->flush();

        // Article
        $article = new Article('New Software Technology', 'https://example.com/gp-1', $source, new \DateTimeImmutable());
        $article->setContentText('A major software technology company released new developer tools.');
        $article->setCategory($category);
        $this->em->persist($article);
        $this->em->flush();

        // Enrich
        $contentText = $article->getContentText() ?? '';
        $catResult = self::getContainer()->get(CategorizationServiceInterface::class)->categorize($article->getTitle(), $contentText);
        self::assertNotNull($catResult->value);

        $sumResult = self::getContainer()->get(SummarizationServiceInterface::class)->summarize($contentText);
        self::assertNotNull($sumResult->value);

        // Score
        $score = self::getContainer()->get(ScoringServiceInterface::class)->score($article);
        self::assertGreaterThan(0.0, $score);

        // Alert match
        $matches = self::getContainer()->get(ArticleMatcherServiceInterface::class)->match($article);
        self::assertGreaterThanOrEqual(1, $matches->count());
    }
}
