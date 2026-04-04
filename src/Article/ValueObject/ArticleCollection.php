<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

use App\Article\Entity\Article;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template-extends ArrayCollection<int, Article>
 */
final class ArticleCollection extends ArrayCollection
{
}
