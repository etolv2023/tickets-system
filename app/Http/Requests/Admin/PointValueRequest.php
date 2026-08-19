<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ★ (2026-08-19) F18.3 — the rate card's own validation.
 *
 * These values become money, so the bounds are not decoration:
 *
 *   min:0     a negative rate would invert every penalty in the ledger — a
 *             docked subtask (negative points) times a negative rate is a
 *             bonus for missing a deadline.
 *   max       a ceiling that a plausible rate never reaches but a fat-fingered
 *             extra zero does. It is the difference between a wrong number
 *             somebody notices and a wrong number that gets paid.
 *
 * The keys are ticket_types ids and are deliberately NOT validated here. The
 * controller resolves them with whereKey(), so an id that does not exist simply
 * selects no row and is ignored — there is no path by which an unknown id
 * writes anything. Validating them as well would only turn a harmless no-op
 * into an error page.
 */
class PointValueRequest extends FormRequest
{
    /** No real rate approaches this. A typo does. */
    private const MAX_RATE = 100000;

    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
            'values.*' => ['required', 'numeric', 'min:0', 'max:' . self::MAX_RATE],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'values.*.required' => 'سيبت خانة سعر فاضية — حط صفر لو النوع ده مش مدفوع.',
            'values.*.numeric' => 'سعر النقطة لازم يكون رقم.',
            'values.*.min' => 'سعر النقطة مينفعش يكون بالسالب.',
            'values.*.max' => 'السعر ده كبير أوي — راجع الرقم.',
        ];
    }
}
