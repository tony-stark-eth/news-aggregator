<?php

declare(strict_types=1);

namespace App\Source\Command;

use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Seed default categories and sources',
)]
final class SeedDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $categories = $this->seedCategories($io);
        $this->seedSources($io, $categories);

        $this->entityManager->flush();

        $io->success('Seed data loaded successfully.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(SymfonyStyle $io): array
    {
        $definitions = [
            [
                'name' => 'Politics',
                'slug' => 'politics',
                'weight' => 10,
                'color' => '#EF4444',
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'weight' => 9,
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Tech',
                'slug' => 'tech',
                'weight' => 8,
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'weight' => 7,
                'color' => '#10B981',
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'weight' => 6,
                'color' => '#8B5CF6',
            ],
        ];

        $categories = [];
        $repository = $this->entityManager->getRepository(Category::class);

        foreach ($definitions as $def) {
            $existing = $repository->findOneBy([
                'slug' => $def['slug'],
            ]);
            if ($existing instanceof Category) {
                $io->note(sprintf('Category "%s" already exists, skipping.', $def['name']));
                $categories[$def['slug']] = $existing;

                continue;
            }

            $category = new Category($def['name'], $def['slug'], $def['weight'], $def['color']);
            $this->entityManager->persist($category);
            $categories[$def['slug']] = $category;
            $io->info(sprintf('Created category: %s', $def['name']));
        }

        return $categories;
    }

    /**
     * @param array<string, Category> $categories
     */
    private function seedSources(SymfonyStyle $io, array $categories): void
    {
        $definitions = [
            [
                'name' => 'Tagesschau',
                'url' => 'https://www.tagesschau.de/xml/rss2',
                'category' => 'politics',
            ],
            [
                'name' => 'ZDF heute',
                'url' => 'https://www.zdf.de/rss/zdf/nachrichten',
                'category' => 'politics',
            ],
            [
                'name' => 'BBC News',
                'url' => 'https://feeds.bbci.co.uk/news/rss.xml',
                'category' => 'politics',
            ],
            [
                'name' => 'Der Spiegel',
                'url' => 'https://www.spiegel.de/schlagzeilen/tops/index.rss',
                'category' => 'politics',
            ],
            [
                'name' => 'Handelsblatt',
                'url' => 'https://www.handelsblatt.com/contentexport/feed/top',
                'category' => 'business',
            ],
            [
                'name' => 'MarketWatch',
                'url' => 'https://feeds.content.dowjones.io/public/rss/mw_topstories',
                'category' => 'business',
            ],
            [
                'name' => 'CNBC',
                'url' => 'https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114',
                'category' => 'business',
            ],
            [
                'name' => 'Reuters Business',
                'url' => 'https://www.reutersagency.com/feed/?best-topics=business-finance',
                'category' => 'business',
            ],
            [
                'name' => 'Heise',
                'url' => 'https://www.heise.de/rss/heise-atom.xml',
                'category' => 'tech',
            ],
            [
                'name' => 'Ars Technica',
                'url' => 'https://feeds.arstechnica.com/arstechnica/index',
                'category' => 'tech',
            ],
            [
                'name' => 'The Verge',
                'url' => 'https://www.theverge.com/rss/index.xml',
                'category' => 'tech',
            ],
            [
                'name' => 'Hacker News',
                'url' => 'https://hnrss.org/frontpage',
                'category' => 'tech',
            ],
            [
                'name' => 'Nature News',
                'url' => 'https://www.nature.com/nature.rss',
                'category' => 'science',
            ],
            [
                'name' => 'Ars Technica Science',
                'url' => 'https://feeds.arstechnica.com/arstechnica/science',
                'category' => 'science',
            ],
            [
                'name' => 'Kicker',
                'url' => 'https://rss.kicker.de/news/aktuell',
                'category' => 'sports',
            ],
            [
                'name' => 'ESPN',
                'url' => 'https://www.espn.com/espn/rss/news',
                'category' => 'sports',
            ],
        ];

        $repository = $this->entityManager->getRepository(Source::class);
        $now = $this->clock->now();

        foreach ($definitions as $def) {
            $existing = $repository->findOneBy([
                'feedUrl' => $def['url'],
            ]);
            if ($existing instanceof Source) {
                $io->note(sprintf('Source "%s" already exists, skipping.', $def['name']));

                continue;
            }

            $category = $categories[$def['category']] ?? null;
            if (! $category instanceof Category) {
                $io->warning(sprintf('Category "%s" not found for source "%s", skipping.', $def['category'], $def['name']));

                continue;
            }

            $source = new Source($def['name'], $def['url'], $category, $now);
            $this->entityManager->persist($source);
            $io->info(sprintf('Created source: %s', $def['name']));
        }
    }
}
