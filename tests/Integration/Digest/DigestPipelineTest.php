<?php

declare(strict_types=1);

namespace App\Tests\Integration\Digest;

use App\Article\Entity\Article;
use App\Digest\Entity\DigestConfig;
use App\Digest\Entity\DigestLog;
use App\Digest\Message\GenerateDigestMessage;
use App\Digest\MessageHandler\GenerateDigestHandler;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(GenerateDigestHandler::class)]
final class DigestPipelineTest extends KernelTestCase
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

    public function testDigestPipelineCollectsAndLogs(): void
    {
        // Seed category + source + articles
        $category = new Category('Tech', 'tech-digest', 10, '#3B82F6');
        $this->em->persist($category);

        $source = new Source('Test Source', 'https://example.com/digest-feed', $category, new \DateTimeImmutable());
        $this->em->persist($source);

        $article = new Article('Test Digest Article', 'https://example.com/digest-1', $source, new \DateTimeImmutable());
        $article->setCategory($category);
        $article->setSummary('A test summary for digest.');
        $article->setScore(0.8);
        $this->em->persist($article);

        // Seed user + digest config (never run = always due)
        $user = new User('digest@example.com', 'hashed');
        $this->em->persist($user);

        $config = new DigestConfig('Test Digest', '0 8 * * *', $user, new \DateTimeImmutable());
        $config->setCategories(['tech-digest']);
        $this->em->persist($config);

        $this->em->flush();

        // Run the handler
        $handler = self::getContainer()->get(GenerateDigestHandler::class);
        $configId = $config->getId();
        self::assertNotNull($configId);

        $handler(new GenerateDigestMessage($configId));

        // Verify: DigestLog created
        $logs = $this->em->getRepository(DigestLog::class)->findBy([
            'digestConfig' => $config,
        ]);
        self::assertCount(1, $logs);
        self::assertSame(1, $logs[0]->getArticleCount());
        self::assertStringContainsString('Test Digest Article', $logs[0]->getContent());

        // Verify: lastRunAt updated
        $this->em->refresh($config);
        self::assertNotNull($config->getLastRunAt());
    }

    public function testDigestSkipsWhenNoArticles(): void
    {
        $user = new User('empty@example.com', 'hashed');
        $this->em->persist($user);

        $config = new DigestConfig('Empty Digest', '0 8 * * *', $user, new \DateTimeImmutable());
        $config->setLastRunAt(new \DateTimeImmutable()); // Set to now so no articles match
        $this->em->persist($config);

        $this->em->flush();

        $handler = self::getContainer()->get(GenerateDigestHandler::class);
        $configId = $config->getId();
        self::assertNotNull($configId);

        $handler(new GenerateDigestMessage($configId));

        // Skipped run log is created when no articles
        $logs = $this->em->getRepository(DigestLog::class)->findBy([
            'digestConfig' => $config,
        ]);
        self::assertCount(1, $logs);
        self::assertSame(0, $logs[0]->getArticleCount());
        self::assertFalse($logs[0]->isDeliverySuccess());
        self::assertStringContainsString('Skipped', $logs[0]->getContent());
    }
}
