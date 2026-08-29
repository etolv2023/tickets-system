{{-- F27 — attaching a branch that already exists to this ticket.

     Split out of _github.blade.php rather than nested in it because it is the
     one part of that panel that WRITES, and it is worth being able to read the
     read-only part without scrolling past a form.

     What it does NOT do is create a branch. The name is checked against this
     ticket's number, and then against GitHub — if there is no such branch, the
     submit is refused. That second check is the whole point: without it this is
     a text box for making a ticket look finished. --}}

<hr class="divider">

<form method="POST" action="{{ route('tickets.branches.store', $ticket) }}" class="stack stack--tight">
    @csrf

    <p class="field__hint">
        لو البرانش موجود على جيت هب والنظام ملقاهوش، اربطه من هنا. النظام هيتأكد إنه موجود فعلاً قبل ما يقبله.
    </p>

    <x-field name="github_repository_id" label="الريبو" required>
        <select id="github_repository_id" name="github_repository_id" class="select" required>
            @foreach ($githubRepositories as $repository)
                <option value="{{ $repository->id }}" @selected(old('github_repository_id') == $repository->id)>
                    {{ $repository->name }} — {{ $repository->fullName() }}
                </option>
            @endforeach
        </select>
    </x-field>

    {{-- dir="ltr" and a monospace face: a branch name is read character by
         character and compared against another screen, like every other code
         in this system (CLAUDE.md § 6). --}}
    <x-field name="branch_name" label="اسم البرانش" required
             :value="$suggestedBranch"
             {{-- Not "مثلاً $suggestedBranch": on an Arabic title the suggestion
                  IS the bare number, so the example repeated the rule word for
                  word. The prefix form is the part that is not obvious. --}}
             :hint="'لازم يبدأ بـ ' . $ticket->ticket_number
                    . (config('github.allow_type_prefix') ? '، أو feature/' . $ticket->ticket_number : '')"
             class="u-mono u-ltr" dir="ltr" maxlength="255" />

    <x-button variant="secondary" size="sm" block>اربط البرانش</x-button>
</form>
