<?php

declare(strict_types=1);

namespace App\Source\Service;

interface OpmlExportServiceInterface
{
    public function export(): string;
}
