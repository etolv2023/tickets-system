<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * ★ (2026-08-29) F29 — الدخول بعين مستخدم تاني، والسجل بتاعه.
 *
 * Full impersonation by explicit request: everything done while it is running
 * is done as that person and recorded under their name, because that is what
 * keeps every other screen honest. The price is that the system can no longer
 * say who was really there — so impersonation_sessions says it instead, and
 * every action taken inside a session points back at it.
 *
 * Three things guard this, and none of them is the button being hidden:
 *   · users.impersonate, its own permission, granted to no role by hand
 *   · you cannot impersonate anybody who also holds it (ImpersonationService)
 *   · you cannot start one while one is running — no nesting, below
 */
class ImpersonationController extends Controller
{
    /** Where the running session's id lives between requests. */
    public const SESSION_KEY = 'impersonation.session_id';

    /** Who to put back afterwards. */
    public const ORIGINAL_USER_KEY = 'impersonation.original_user_id';

    /**
     * The log. «مين دخل بعين مين وعمل إيه» — one screen, one join.
     *
     * On audit.view rather than users.impersonate: doing it and auditing it are
     * different jobs, and the second must not require the first.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        return view('admin.impersonations.index', [
            'sessions' => ImpersonationSession::query()
                ->with(['impersonator:id,name,avatar_path,is_active', 'impersonated:id,name,avatar_path,is_active'])
                ->latest('started_at')
                ->paginate(30),
        ]);
    }

    /** Everything logged during one session, oldest first — the "عمل إيه" half. */
    public function show(Request $request, ImpersonationSession $impersonation): View
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        $impersonation->load(['impersonator:id,name,avatar_path,is_active', 'impersonated:id,name,avatar_path,is_active']);

        return view('admin.impersonations.show', [
            'session' => $impersonation,
            'actions' => $impersonation->actions()
                ->with('user:id,name')
                ->orderBy('created_at')
                ->paginate(50),
        ]);
    }

    public function store(Request $request, User $user, ImpersonationService $impersonation): RedirectResponse
    {
        // No nesting. The other half of the identity-laundering guard in
        // ImpersonationService: even a target who does not hold the permission
        // must not be a step towards a third account.
        if ($request->session()->has(self::SESSION_KEY)) {
            return back()->withErrors(['impersonate' => 'انت داخل بعين حد بالفعل. ارجع لحسابك الأول.']);
        }

        $actor = $request->user();

        try {
            $session = $impersonation->start($actor, $user, $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            return back()->withErrors(['impersonate' => $e->getMessage()]);
        }

        /*
         * regenerate() before the swap, not after: the id that carried the
         * administrator's session must not also carry the borrowed one.
         * put() comes after, or regeneration would drop what we just stored.
         */
        $request->session()->regenerate();

        Auth::login($user);

        $request->session()->put(self::SESSION_KEY, $session->id);
        $request->session()->put(self::ORIGINAL_USER_KEY, $actor->id);

        return redirect()->route('home');
    }

    /** Back to your own face. */
    public function destroy(Request $request, ImpersonationService $impersonation): RedirectResponse
    {
        $sessionId = $request->session()->pull(self::SESSION_KEY);
        $originalId = $request->session()->pull(self::ORIGINAL_USER_KEY);

        if ($sessionId !== null) {
            $session = ImpersonationSession::find($sessionId);

            if ($session !== null) {
                $impersonation->stop($session);
            }
        }

        $original = $originalId === null ? null : User::find($originalId);

        /*
         * A missing or deactivated original is the one case with no safe way
         * back: putting them in would be logging in an account that may have
         * been switched off while the session ran. Out entirely instead.
         */
        if ($original === null || ! $original->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $request->session()->regenerate();
        Auth::login($original);

        return redirect()->route('admin.users.index')
            ->with('status', 'رجعت لحسابك.');
    }
}
