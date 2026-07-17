<?php

namespace App\Enums;

/** The two sides a ticket can be committed to. F07 */
enum WorkSide: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';

    public function label(): string
    {
        return match ($this) {
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
        };
    }

    /** The tickets column holding this side's assignee. */
    public function assigneeColumn(): string
    {
        return match ($this) {
            self::Frontend => 'assigned_frontend_id',
            self::Backend => 'assigned_backend_id',
        };
    }
}
