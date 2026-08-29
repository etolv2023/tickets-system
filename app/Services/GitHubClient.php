<?php

namespace App\Services;

use App\Models\GithubRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talking to GitHub, and nothing else — F27.
 *
 * ══════════════════════════════════════════════════════════════════════════
 *  THIS CLASS ISSUES GET REQUESTS. THERE IS NO OTHER VERB ANYWHERE IN IT.
 * ══════════════════════════════════════════════════════════════════════════
 *
 * That is the guarantee, and it is structural rather than a matter of
 * discipline: there is exactly one method that reaches the network, request(),
 * it is private, and it calls ->get(). Nothing in this application can create a
 * branch, move a ref, open or merge a pull request, or delete anything on
 * GitHub, because no code exists that could express it.
 *
 * The token backing this should be a fine-grained PAT with Contents: Read-only
 * and Pull requests: Read-only, so the same guarantee also holds one layer
 * lower, where a future edit to this file could not reach. `github:check`
 * verifies that and complains if the token turns out to hold more.
 *
 * Deliberately the same shape as DiscordService: a configured() guard, one
 * timeout, and no knowledge whatsoever of what a ticket is — that belongs to
 * GitHubSyncService. Unlike Discord there is no queue and no ledger, because
 * nothing here is a delivery that could half-happen. A read either returns data
 * or is tried again tomorrow.
 */
class GitHubClient
{
    /** GitHub caps a page at 100 items on every endpoint used here. */
    private const PER_PAGE = 100;

    public function configured(): bool
    {
        return (bool) config('github.enabled') && filled(config('github.token'));
    }

    /**
     * Who the token belongs to. The cheapest possible "does this work at all".
     *
     * @return array<string, mixed>|null null when the token is rejected
     */
    public function viewer(): ?array
    {
        $response = $this->request('/user');

        return $response->status() === 401 ? null : $this->json($response, '/user');
    }

    /**
     * The repository object, which carries the `permissions` block telling us
     * what this token can do to it. That block is how github:check detects a
     * token holding more than read.
     *
     * @return array<string, mixed>|null null when it is missing or invisible
     */
    public function repository(GithubRepository $repo): ?array
    {
        $response = $this->request('/repos/' . $repo->fullName());

        if ($response->status() === 404) {
            return null;
        }

        return $this->json($response, $repo->fullName());
    }

    /**
     * Every branch in the repository — name and head sha, nothing more.
     *
     * The list endpoint does not carry the commit's date or author, only its
     * sha. That is deliberate on GitHub's side and convenient here: the sync
     * compares the sha it already holds and only asks for the detail of
     * branches whose head actually moved.
     *
     * @return array<int, array<string, mixed>>
     */
    public function branches(GithubRepository $repo): array
    {
        return $this->paginate('/repos/' . $repo->fullName() . '/branches');
    }

    /**
     * One branch, with its head commit's author and date.
     *
     * Returns null on 404, which is the answer the manual-link validator is
     * actually asking for: "does this branch exist?". A missing branch is not
     * an error condition here, it is a No.
     *
     * @return array<string, mixed>|null
     */
    public function branch(GithubRepository $repo, string $name): ?array
    {
        // Each segment encoded separately — a branch name may contain slashes,
        // and they are path separators in this URL, not characters to escape.
        $path = implode('/', array_map('rawurlencode', explode('/', $name)));

        $response = $this->request('/repos/' . $repo->fullName() . '/branches/' . $path);

        if (in_array($response->status(), [404, 301], true)) {
            return null;
        }

        return $this->json($response, $repo->fullName() . '#' . $name);
    }

    /**
     * Pull requests, newest change first, stopping once they get older than
     * $since.
     *
     * Sorted by update time rather than fetched wholesale because `state=all`
     * on a repository with years of history is thousands of rows that have not
     * changed since the last sync. The first page whose oldest entry predates
     * $since ends the walk.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pullRequests(GithubRepository $repo, ?CarbonInterface $since = null): array
    {
        $path = '/repos/' . $repo->fullName() . '/pulls';
        $query = ['state' => 'all', 'sort' => 'updated', 'direction' => 'desc'];

        if ($since === null) {
            return $this->paginate($path, $query);
        }

        $collected = [];

        foreach ($this->pages($path, $query) as $page) {
            foreach ($page as $pull) {
                $updated = $pull['updated_at'] ?? null;

                if ($updated !== null && CarbonImmutable::parse($updated)->lessThan($since)) {
                    return $collected;
                }

                $collected[] = $pull;
            }
        }

        return $collected;
    }

    /**
     * When the token stops working, if GitHub tells us.
     *
     * A fine-grained PAT reports its expiry in a response header. The failure
     * mode when one lapses is silent and misleading — the sync simply stops
     * finding branches, and every ticket starts looking like it has no code —
     * so github:check reads this and warns ahead of time.
     */
    public function tokenExpiresAt(): ?CarbonImmutable
    {
        $header = $this->request('/user')->header('github-authentication-token-expiration');

        if (blank($header)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($header);
        } catch (\Throwable) {
            // A header we cannot read is not worth failing a check over.
            return null;
        }
    }

    /**
     * Walk pages until one comes back short.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function paginate(string $path, array $query = []): array
    {
        $all = [];

        foreach ($this->pages($path, $query) as $page) {
            $all = array_merge($all, $page);
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Generator<int, array<int, array<string, mixed>>>
     */
    private function pages(string $path, array $query = []): \Generator
    {
        $max = max(1, (int) config('github.max_pages', 10));

        for ($page = 1; $page <= $max; $page++) {
            $response = $this->request($path, $query + ['per_page' => self::PER_PAGE, 'page' => $page]);

            $items = $this->json($response, $path);

            if (! is_array($items) || $items === []) {
                return;
            }

            yield $items;

            // A short page is the last page. Cheaper and more reliable than
            // parsing the Link header, and it cannot loop forever.
            if (count($items) < self::PER_PAGE) {
                return;
            }
        }

        /*
         * ★ (2026-08-29) Reaching the cap with a FULL last page means there is
         * more and we stopped anyway. That used to return quietly, and the first
         * real sync came back with exactly 1000 branches on trioapi — the cap,
         * read as a count. Every branch past it was invisible, so every ticket
         * whose branch lived there was reported as having no code behind it.
         *
         * Refusing is the right answer, not truncating. A sync that fails leaves
         * yesterday's data and says why on the repository row; a sync that
         * silently returns half the branches produces confident, wrong
         * accusations about people's work, and nothing on any screen looks off.
         */
        throw new RuntimeException(
            'الريبو ده فيه أكتر من ' . ($max * self::PER_PAGE) . ' صف على ' . $path
            . ' — الحد الحالي (GITHUB_MAX_PAGES=' . $max . ') مش كفاية، وقراءة ناقصة معناها '
            . 'تذاكر تبان من غير برانش وهي ليها. ارفع الحد وشغّل المزامنة تاني.'
        );
    }

    /**
     * The only method in this class that reaches the network, and the only
     * place a verb is chosen. It is ->get(). See the class docblock.
     *
     * @param  array<string, mixed>  $query
     */
    private function request(string $path, array $query = []): Response
    {
        if (! $this->configured()) {
            throw new RuntimeException('تكامل جيت هب مش مفعّل أو التوكن ناقص.');
        }

        try {
            return $this->http()->get(rtrim(config('github.api_base'), '/') . $path, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException('مفيش اتصال بجيت هب: ' . $e->getMessage(), previous: $e);
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken(config('github.token'))
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                // Pinned: an unpinned client silently changes shape the day
                // GitHub ships a new default version.
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->timeout((int) config('github.timeout', 15))
            ->acceptJson();
    }

    /**
     * Turn a response into data, or into an explanation of why there is none.
     *
     * Every failure gets a message a person can act on. "403" tells an
     * administrator nothing; "the token cannot see etolv2023/trioapi" tells
     * them to fix the token's repository list.
     */
    private function json(Response $response, string $context): mixed
    {
        if ($response->successful()) {
            return $response->json();
        }

        // Rate limiting arrives as 403 or 429 with the remaining count at zero,
        // which is a different problem from a permission being missing.
        if ($response->header('x-ratelimit-remaining') === '0') {
            $reset = $response->header('x-ratelimit-reset');

            throw new RuntimeException(
                'حد الطلبات على جيت هب خلص'
                . ($reset ? '، هيرجع الساعة ' . CarbonImmutable::createFromTimestamp((int) $reset)->format('H:i') : '')
                . '.'
            );
        }

        throw new RuntimeException(match ($response->status()) {
            401 => 'توكن جيت هب مرفوض — غالباً خلصت صلاحيته أو اتلغى.',
            403 => 'توكن جيت هب مش مسموح له بـ ' . $context . ' — راجع الريبوز المختارة في التوكن.',
            404 => $context . ' مش موجود، أو التوكن مش شايفه.',
            default => 'جيت هب رجّع ' . $response->status() . ' على ' . $context . '.',
        });
    }
}
