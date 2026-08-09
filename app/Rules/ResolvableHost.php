<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel's active_url, minus the assumption that every customer is on the
 * public internet.
 *
 * ★ (2026-08-04) A ticket's page link is DNS-checked so a typo is caught while
 * the person who knows the right answer is still on the form. But active_url
 * does one thing — dns_get_record on the host — and treats "no record" as
 * "broken". For a customer running their ERP at 192.168.1.50, or at
 * erp.company.local, there is no public record and never will be, so the check
 * would refuse a link that works fine from where it is used. That would have
 * blocked opening a ticket for that customer at all.
 *
 * So the lookup is skipped for hosts that are internal by definition, and kept
 * for everything else. Skipping is not the same as accepting anything: the
 * scheme is still pinned by the `url:http,https` rule next to this one, and a
 * public host that does not resolve is still refused.
 *
 * Three shapes are internal without needing to be configured:
 *   - an IP literal, v4 or v6. Public or private, a name server is not going to
 *     have an A record for a number, so asking is meaningless either way.
 *   - a single-label host (http://erp/), which only resolves on a network that
 *     appends its own search domain.
 *   - a host whose last label is in config('tickets.internal_host_suffixes').
 */
class ResolvableHost implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = parse_url((string) $value, PHP_URL_HOST);

        // Not a parseable URL. The `url` rule beside this one owns that message;
        // saying it twice would put two errors under one field.
        if (! is_string($host) || $host === '') {
            return;
        }

        $host = rtrim($host, '.');

        if ($this->isInternal($host) || $this->resolves($host)) {
            return;
        }

        $fail('الدومين ده مش موجود — راجع اللينك.');
    }

    private function isInternal(string $host): bool
    {
        // parse_url keeps the brackets on an IPv6 literal: http://[::1]/x
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! str_contains($host, '.')) {
            return true;
        }

        $suffix = mb_strtolower(mb_substr($host, mb_strrpos($host, '.') + 1));

        return in_array($suffix, (array) config('tickets.internal_host_suffixes', []), true);
    }

    private function resolves(string $host): bool
    {
        // The trailing dot is what Laravel's own active_url does, and it
        // matters: without it the resolver may append this machine's search
        // domain, so a typo'd host "resolves" on one network and not another.
        $records = @dns_get_record($host . '.', DNS_A | DNS_AAAA);

        return is_array($records) && $records !== [];
    }
}
