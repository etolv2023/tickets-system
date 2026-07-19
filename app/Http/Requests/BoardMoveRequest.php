<?php

namespace App\Http\Requests;

use App\Http\Controllers\BoardController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('ticket'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // The browser sends a column key, not a status: two of the four columns
        // hold more than one status, and two of them are driven by work logs
        // rather than by a status write at all.
        return ['column' => ['required', Rule::in(array_keys(BoardController::COLUMNS))]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['column' => 'العمود'];
    }
}
