<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<PriorityValue, PriorityValue|string> */
class PriorityCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PriorityValue
    {
        return $value === null ? null : PriorityValue::for($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof PriorityValue ? $value->value : (string) $value;
    }
}
