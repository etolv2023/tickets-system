<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<TicketTypeValue, TicketTypeValue|string> */
class TicketTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TicketTypeValue
    {
        return $value === null ? null : TicketTypeValue::for($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof TicketTypeValue ? $value->value : (string) $value;
    }
}
