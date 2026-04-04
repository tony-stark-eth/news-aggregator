<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Entity;

use App\Shared\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Category::class)]
final class CategoryRepositoryTest extends KernelTestCase
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

    public function testPersistAndFind(): void
    {
        $category = new Category('Integration Test', 'integration-test', 5, '#FF0000');
        $this->em->persist($category);
        $this->em->flush();

        $id = $category->getId();
        self::assertNotNull($id);

        $this->em->clear();

        $found = $this->em->getRepository(Category::class)->find($id);
        self::assertInstanceOf(Category::class, $found);
        self::assertSame('Integration Test', $found->getName());
        self::assertSame('integration-test', $found->getSlug());
        self::assertSame(5, $found->getWeight());
        self::assertSame('#FF0000', $found->getColor());
    }

    public function testFindBySlug(): void
    {
        $category = new Category('Slug Test', 'slug-test', 3, '#00FF00');
        $this->em->persist($category);
        $this->em->flush();
        $this->em->clear();

        $found = $this->em->getRepository(Category::class)->findOneBy([
            'slug' => 'slug-test',
        ]);
        self::assertInstanceOf(Category::class, $found);
        self::assertSame('Slug Test', $found->getName());
    }
}
