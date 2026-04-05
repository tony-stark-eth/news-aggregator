<?php

declare(strict_types=1);

namespace App\Source\Command;

use App\Digest\Entity\DigestConfig;
use App\Digest\Repository\DigestConfigRepositoryInterface;
use App\Shared\Entity\Category;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Seed default categories and sources',
)]
final class SeedDataCommand extends Command
{
    /**
     * @param non-empty-string $adminEmail
     * @param non-empty-string $adminPassword
     */
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DigestConfigRepositoryInterface $digestConfigRepository,
        private readonly ClockInterface $clock,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $adminEmail = 'demo@localhost',
        private readonly string $adminPassword = 'demo',
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $categories = $this->seedCategories($io);
        $this->seedSources($io, $categories);
        $user = $this->seedDemoUser($io);
        $this->seedDigestConfigs($io, $user);

        $this->categoryRepository->flush();

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
                'fetchInterval' => 5,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'weight' => 9,
                'color' => '#F59E0B',
                'fetchInterval' => 10,
            ],
            [
                'name' => 'Tech',
                'slug' => 'tech',
                'weight' => 8,
                'color' => '#3B82F6',
                'fetchInterval' => 15,
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'weight' => 7,
                'color' => '#10B981',
                'fetchInterval' => 60,
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'weight' => 6,
                'color' => '#8B5CF6',
                'fetchInterval' => 30,
            ],
        ];

        $categories = [];

        foreach ($definitions as $def) {
            $existing = $this->categoryRepository->findBySlug($def['slug']);
            if ($existing instanceof Category) {
                $io->note(sprintf('Category "%s" already exists, skipping.', $def['name']));
                $categories[$def['slug']] = $existing;

                continue;
            }

            $category = new Category($def['name'], $def['slug'], $def['weight'], $def['color']);
            $category->setFetchIntervalMinutes($def['fetchInterval']);
            $this->categoryRepository->save($category);
            $categories[$def['slug']] = $category;
            $io->info(sprintf('Created category: %s (fetch every %d min)', $def['name'], $def['fetchInterval']));
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
                'language' => 'de',
            ],
            [
                'name' => 'ZDF heute',
                'url' => 'https://www.zdf.de/rss/zdf/nachrichten',
                'category' => 'politics',
                'language' => 'de',
            ],
            [
                'name' => 'BBC News',
                'url' => 'https://feeds.bbci.co.uk/news/rss.xml',
                'category' => 'politics',
                'language' => 'en',
            ],
            [
                'name' => 'Le Monde',
                'url' => 'https://www.lemonde.fr/rss/une.xml',
                'category' => 'politics',
                'language' => 'fr',
            ],
            [
                'name' => 'Der Spiegel',
                'url' => 'https://www.spiegel.de/schlagzeilen/tops/index.rss',
                'category' => 'politics',
                'language' => 'de',
            ],
            [
                'name' => 'Handelsblatt',
                'url' => 'https://www.handelsblatt.com/contentexport/feed/schlagzeilen',
                'category' => 'business',
                'language' => 'de',
            ],
            [
                'name' => 'MarketWatch',
                'url' => 'https://feeds.content.dowjones.io/public/rss/mw_topstories',
                'category' => 'business',
                'language' => 'en',
            ],
            [
                'name' => 'CNBC',
                'url' => 'https://search.cnbc.com/rs/search/combinedcms/view.xml?partnerId=wrss01&id=100003114',
                'category' => 'business',
                'language' => 'en',
            ],
            [
                'name' => 'Yahoo Finance',
                'url' => 'https://finance.yahoo.com/news/rssindex',
                'category' => 'business',
                'language' => 'en',
            ],
            [
                'name' => 'Heise',
                'url' => 'https://www.heise.de/rss/heise-atom.xml',
                'category' => 'tech',
                'language' => 'de',
            ],
            [
                'name' => 'Ars Technica',
                'url' => 'https://feeds.arstechnica.com/arstechnica/index',
                'category' => 'tech',
                'language' => 'en',
            ],
            [
                'name' => 'The Verge',
                'url' => 'https://www.theverge.com/rss/index.xml',
                'category' => 'tech',
                'language' => 'en',
            ],
            [
                'name' => 'Hacker News',
                'url' => 'https://hnrss.org/frontpage',
                'category' => 'tech',
                'language' => 'en',
            ],
            [
                'name' => 'PHP Reads',
                'url' => 'https://phpreads.com/feed',
                'category' => 'tech',
                'language' => 'en',
            ],
            [
                'name' => 'Nature News',
                'url' => 'https://www.nature.com/nature.rss',
                'category' => 'science',
                'language' => 'en',
            ],
            [
                'name' => 'Ars Technica Science',
                'url' => 'https://feeds.arstechnica.com/arstechnica/science',
                'category' => 'science',
                'language' => 'en',
            ],
            [
                'name' => 'Kicker',
                'url' => 'https://newsfeed.kicker.de/news/aktuell',
                'category' => 'sports',
                'language' => 'de',
            ],
            [
                'name' => 'ESPN',
                'url' => 'https://www.espn.com/espn/rss/news',
                'category' => 'sports',
                'language' => 'en',
            ],
        ];

        $now = $this->clock->now();

        foreach ($definitions as $def) {
            $existing = $this->sourceRepository->findByFeedUrl($def['url']);
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
            $source->setLanguage($def['language']);
            $this->sourceRepository->save($source);
            $io->info(sprintf('Created source: %s', $def['name']));
        }
    }

    private function seedDemoUser(SymfonyStyle $io): User
    {
        $existing = $this->userRepository->findFirst();
        if ($existing instanceof User) {
            $io->note('Demo user already exists, skipping.');

            return $existing;
        }

        $user = new User($this->adminEmail, '');
        $hashedPassword = $this->passwordHasher->hashPassword($user, $this->adminPassword);
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_ADMIN']);
        $this->userRepository->save($user);
        $io->info(sprintf('Created admin user: %s', $this->adminEmail));

        return $user;
    }

    private function seedDigestConfigs(SymfonyStyle $io, User $user): void
    {
        $now = $this->clock->now();

        $definitions = [
            [
                'name' => 'Daily Tech Digest',
                'schedule' => '0 8 * * *',
                'categories' => ['tech'],
                'limit' => 10,
            ],
            [
                'name' => 'Weekly Summary',
                'schedule' => '0 9 * * 1',
                'categories' => [],
                'limit' => 20,
            ],
        ];

        foreach ($definitions as $def) {
            $existing = $this->digestConfigRepository->findByNameAndUser($def['name'], $user);

            if ($existing instanceof DigestConfig) {
                $io->note(sprintf('Digest config "%s" already exists, skipping.', $def['name']));

                continue;
            }

            $config = new DigestConfig($def['name'], $def['schedule'], $user, $now);
            $config->setCategories($def['categories']);
            $config->setArticleLimit($def['limit']);
            $this->digestConfigRepository->save($config);
            $io->info(sprintf('Created digest config: %s (%s)', $def['name'], $def['schedule']));
        }
    }
}
