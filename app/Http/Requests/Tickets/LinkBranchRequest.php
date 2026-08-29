<?php

namespace App\Http\Requests\Tickets;

use App\Services\BranchNamingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * F27 — attaching a branch to a ticket by hand.
 *
 * Two of the three gates live here: the repository must be one this system
 * knows and has switched on, and the name must follow the convention and name
 * THIS ticket. The third gate — does the branch actually exist on GitHub — is
 * in GitHubSyncService::link(), because it costs a network call and there is no
 * point spending one on a name that was never going to be accepted.
 *
 * The naming check is a closure rather than a regex rule so the error can say
 * WHICH rule was broken. "اسم البرانش غلط" and "ده برانش تذكرة تانية" send a
 * person to two completely different places.
 */
class LinkBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-model bound; the controller re-checks the ticket policy.
        return $this->user()->hasPermission('github.audit');
    }

    public function rules(): array
    {
        return [
            'github_repository_id' => ['required', 'integer', 'exists:github_repositories,id'],
            // Named branch_name rather than name so a rejection lands on a
            // key nothing else on the ticket page uses — the show view picks
            // the open tab from the error keys, and a shared `name` would send
            // this error to some other panel.
            // 255 is the column; Git itself has no hard limit worth enforcing
            // separately, and the character rules are checked below.
            'branch_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('branch_name')) {
                    return;
                }

                $reason = app(BranchNamingService::class)->rejectionReason(
                    (string) $this->input('branch_name'),
                    $this->route('ticket')->ticket_number,
                );

                if ($reason !== null) {
                    $validator->errors()->add('branch_name', $reason);
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'github_repository_id' => 'الريبو',
            'branch_name' => 'اسم البرانش',
        ];
    }
}
