<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<LinkTypeValue, LinkTypeValue|string> */
class LinkTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?LinkTypeValue
    {
        return $value === null ? null : LinkTypeValue::for($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof LinkTypeValue ? $value->value : (string) $value;
    }
}
