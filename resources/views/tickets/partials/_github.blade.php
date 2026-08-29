{{-- F27 — الكود. Read-only: everything here was read from GitHub, and the one
     form on the panel writes a link in THIS database, never a branch there.

     No @php block: the suggested name and the repository list both arrive from
     the controller, because a naming convention is a business rule and Blade
     does not hold those (CLAUDE.md § 3). --}}

<x-card title="البرانشات">
    <div class="stack stack--tight">
        @if ($ticket->branches->isEmpty())
            <div class="gh-empty">
                <p class="gh-empty__title">التذكرة دي ملهاش برانش لسه.</p>
                <p class="field__hint">
                    البرانش لازم يبدأ برقم التذكرة عشان النظام يلاقيه لوحده.
                </p>

                <x-copy-row label="الاسم المقترح" :value="$suggestedBranch" />
                <x-copy-row label="الأمر" :value="'git checkout -b ' . $suggestedBranch" />
            </div>
        @else
            <div class="gh-list">
                @foreach ($ticket->branches as $branch)
                    <div class="gh-branch">
                        <div class="gh-branch__head">
                            <span class="gh-branch__repo">{{ $branch->repo()?->name ?? '—' }}</span>

                            @if ($branch->url())
                                <a class="gh-branch__name u-mono u-ltr" href="{{ $branch->url() }}"
                                   target="_blank" rel="noopener noreferrer" title="{{ $branch->name }}">
                                    <x-icon name="external" size="0.9em" />
                                    {{ $branch->name }}
                                </a>
                            @else
                                <span class="gh-branch__name u-mono u-ltr">{{ $branch->name }}</span>
                            @endif

                            <x-badge :variant="$branch->state->variant()">{{ $branch->state->label() }}</x-badge>

                            @if ($branch->isManual())
                                {{-- Worth showing: this row is somebody's
                                     assertion, checked against GitHub, rather
                                     than something the sync found on its own. --}}
                                <x-badge variant="slate">اترّبط بإيد</x-badge>
                            @endif
                        </div>

                        <div class="gh-branch__meta">
                            @if ($branch->author_login)
                                <span class="u-mono u-ltr">{{ '@' . $branch->author_login }}</span>
                            @endif

                            @if ($branch->last_commit_at)
                                <span class="u-mono">
                                    آخر كوميت
                                    {{ $branch->last_commit_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                                </span>
                            @endif

                            @if ($branch->head_sha)
                                <span class="u-mono u-ltr">{{ substr($branch->head_sha, 0, 7) }}</span>
                            @endif
                        </div>

                        <x-copy-row label="شغّله عندك" :value="$branch->checkoutCommand()" />
                    </div>
                @endforeach
            </div>
        @endif

        @if ($ticket->pullRequests->isNotEmpty())
            <hr class="divider">

            <div class="gh-list">
                @foreach ($ticket->pullRequests as $pull)
                    <div class="gh-pull">
                        <a class="gh-pull__number u-mono u-ltr" href="{{ $pull->url() }}"
                           target="_blank" rel="noopener noreferrer">
                            <x-icon name="external" size="0.9em" />
                            #{{ $pull->number }}
                        </a>

                        <span class="gh-pull__title">{{ Str::limit($pull->title, 60) }}</span>

                        <x-badge :variant="$pull->state->variant()">{{ $pull->state->label() }}</x-badge>

                        @if ($pull->is_draft)
                            <x-badge variant="slate">مسودة</x-badge>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if (! config('github.enabled'))
            <p class="field__hint">تكامل جيت هب مقفول، فمفيش حاجة بتتقرا دلوقتي.</p>
        @endif

        @if ($githubRepositories !== [])
            @include('tickets.partials._github-link-form')
        @endif
    </div>
</x-card>
