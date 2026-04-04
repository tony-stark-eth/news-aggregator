<?php

declare(strict_types=1);

namespace App\Shared\AI\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @template-extends ArrayCollection<int, ModelId>
 */
final class ModelIdCollection extends ArrayCollection
{
}
