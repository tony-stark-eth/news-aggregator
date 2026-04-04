<?php

declare(strict_types=1);

namespace App\Shared\AI\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template-extends ArrayCollection<string, ModelQualityStats>
 */
final class ModelQualityStatsMap extends ArrayCollection
{
}
