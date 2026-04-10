<?php

declare(strict_types=1);

namespace App\Source\Service;

use App\Source\Dto\OpmlImportResultDto;

interface OpmlImportServiceInterface
{
    public function import(string $opmlContent): OpmlImportResultDto;
}
