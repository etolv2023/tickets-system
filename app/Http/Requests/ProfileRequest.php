<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ★ (2026-08-19) F26.1 — the small set of facts a person may change about
 * themselves.
 *
 * Deliberately one field. Name, email, role and capacity all stay with
 * users.manage: they decide who someone is and what they are owed, and letting
 * people edit their own would put the assignment lists and the capacity meter
 * in the hands of whoever wants a lighter week. A Discord id is different — it
 * is a contact detail whose only use is reaching that same person, and nobody
 * but them knows it.
 *
 * No authorize() override is needed beyond being signed in: every user may edit
 * their own row and only their own, which the controller guarantees by reading
 * $request->user() rather than an id from the URL. There is no route parameter
 * here to tamper with.
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // A Discord snowflake: digits only, currently 17–19 long, with room
            // left for the 20th. Validated because the failure it prevents is
            // invisible — a malformed id does not error, it renders in the
            // message as literal "<@oops>" text and simply notifies nobody.
            'discord_user_id' => ['nullable', 'string', 'regex:/^\d{17,20}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'discord_user_id.regex' => 'ده لازم يكون الـ ID الرقمي بتاعك (17 لـ 20 رقم) — مش اسم المستخدم. شغّل Developer Mode في ديسكورد، وبعدين كليك يمين على اسمك واختار «Copy User ID».',
        ];
    }

    protected function prepareForValidation(): void
    {
        $id = $this->input('discord_user_id');

        // A pasted id often arrives wrapped as <@123…> (Discord's own mention
        // format) or with stray spaces, and refusing those would be pedantry
        // about a copy-paste rather than about the value. Anything left that
        // is not a bare number still fails the rule above.
        if (is_string($id)) {
            $id = trim($id);
            $id = preg_replace('/^<@!?(\d+)>$/', '$1', $id);
            $this->merge(['discord_user_id' => $id === '' ? null : $id]);
        }
    }
}
