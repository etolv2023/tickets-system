<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubtaskSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * F27 — adding or editing one of the repositories.
 *
 * owner and repo are validated against GitHub's own naming rules rather than
 * left as free text: a typo in either produces a 404 on every sync forever, and
 * a 404 is indistinguishable from "the token cannot see this repo", so the
 * mistake is expensive to diagnose later and free to prevent here.
 *
 * The (owner, repo) pair is unique — the same repository twice would sync
 * twice and could not, because ticket_branches is keyed on the repository id.
 */
class GithubRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        $id = $this->route('repository')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'owner' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/'],
            'repo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('github_repositories', 'repo')
                    ->where('owner', (string) $this->input('owner'))
                    ->ignore($id),
            ],
            'side' => ['nullable', Rule::in(array_column(SubtaskSide::cases(), 'value'))],
            'default_branch' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'owner' => 'المالك',
            'repo' => 'الريبو',
            'side' => 'الجانب',
            'default_branch' => 'الفرع الافتراضي',
        ];
    }

    public function messages(): array
    {
        return [
            'owner.regex' => 'اسم المالك على جيت هب حروف وأرقام وشرطة بس.',
            'repo.regex' => 'اسم الريبو حروف وأرقام و . _ - بس.',
            'repo.unique' => 'الريبو ده مضاف قبل كده.',
        ];
    }
}
