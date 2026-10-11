<?php

namespace App\Services;

use App\Models\GithubRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The deliberately tiny write side of the GitHub integration.
 *
 * Sync, lookup and exception attribution remain in GitHubClient with the
 * read-only token. This client can express exactly one mutation: deleting one
 * named branch after the controller has authorised and confirmed it.
 */
class GitHubWriteClient
{
    public function configured(): bool
    {
        return filled(config('github.write_token'));
    }

    public function deleteBranch(GithubRepository $repository, string $branch): void
    {
        if (! $this->configured()) {
            throw new RuntimeException(__('رمز حذف الفروع في GitHub غير مسجل على الخادم.'));
        }

        $ref = implode('/', array_map('rawurlencode', explode('/', $branch)));
        $url = rtrim(config('github.api_base'), '/')
            . '/repos/' . $repository->fullName() . '/git/refs/heads/' . $ref;

        try {
            $response = Http::withToken(config('github.write_token'))
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->timeout(max(1, (int) config('github.timeout', 15)))
                ->delete($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException(__('تعذّر الاتصال بـ GitHub: :error', ['error' => $e->getMessage()]), previous: $e);
        }

        if ($response->successful()) {
            return;
        }

        $message = $response->json('message');
        throw new RuntimeException(
            __('رفض GitHub حذف الفرع (:status): :message', [
                'status' => $response->status(),
                'message' => is_string($message) ? $message : __('راجع صلاحية Contents وإعدادات حماية الفرع.'),
            ])
        );
    }
}
