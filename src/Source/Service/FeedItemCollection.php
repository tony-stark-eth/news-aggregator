<?php

declare(strict_types=1);

namespace App\Source\Service;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template-extends ArrayCollection<int, FeedItem>
 */
final class FeedItemCollection extends ArrayCollection
{
}
