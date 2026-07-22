<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<SubtaskStatusValue, SubtaskStatusValue|string> */
class SubtaskStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?SubtaskStatusValue
    {
        return $value === null ? null : SubtaskStatusValue::for($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof SubtaskStatusValue ? $value->value : (string) $value;
    }
}
