<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

enum LanguageName: string
{
    case English = 'en';
    case German = 'de';
    case French = 'fr';
    case Spanish = 'es';

    public function label(): string
    {
        return $this->name;
    }

    public static function labelFor(string $code): string
    {
        $lang = self::tryFrom($code);

        return $lang?->label() ?? $code;
    }
}
