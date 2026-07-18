<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<TicketStatusValue, TicketStatusValue|string> */
class TicketStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TicketStatusValue
    {
        return $value === null ? null : TicketStatusValue::for($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof TicketStatusValue ? $value->value : (string) $value;
    }
}
