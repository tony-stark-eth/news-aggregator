<?php

declare(strict_types=1);

namespace App\Notification\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template-extends ArrayCollection<int, MatchResult>
 */
final class MatchResultCollection extends ArrayCollection
{
}
