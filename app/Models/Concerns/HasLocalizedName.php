<?php

namespace App\Models\Concerns;

/**
 * For rows that carry an `<field>_ar` / `<field>_en` pair. Arabic is the
 * required one; English is optional and falls back to Arabic when blank, so a
 * custom row an admin never translated still renders instead of going empty.
 */
trait HasLocalizedName
{
    public function label(): string
    {
        return $this->localized('name') ?? (string) $this->getAttribute('key');
    }

    public function localized(string $field): ?string
    {
        $en = $this->getAttribute("{$field}_en");

        return app()->isLocale('en') && filled($en) ? $en : $this->getAttribute("{$field}_ar");
    }
}
