<?php

namespace App\Http\Controllers;

use App\Http\Middleware\UseInstallConnection;
use App\Http\Requests\Install\AdminRequest;
use App\Http\Requests\Install\DatabaseRequest;
use App\Models\Role;
use App\Services\ActivityLogger;
use App\Services\InstallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * The 5-step wizard (F00.1). Reachable only while installed.lock is absent —
 * EnsureInstalled 404s every route here once it exists.
 *
 * Every route in this group runs behind UseInstallConnection, which points the
 * request at the database step 2 verified.
 */
class InstallController extends Controller
{
    public function __construct(private readonly InstallService $installer)
    {
    }

    public function requirements(): View
    {
        return view('install.requirements', [
            'step' => 1,
            'result' => $this->installer->checkRequirements(),
        ]);
    }

    public function database(): View
    {
        return view('install.database', [
            'step' => 2,
            // Prefilled from whatever .env already holds, so a re-run is quick.
            'defaults' => [
                'db_host' => config('database.connections.mysql.host', '127.0.0.1'),
                'db_port' => config('database.connections.mysql.port', '3306'),
                'db_database' => config('database.connections.mysql.database', ''),
                'db_username' => config('database.connections.mysql.username', 'root'),
            ],
        ]);
    }

    /**
     * Tests the credentials, creates the schema if needed, then writes .env.
     * Writing here rather than at step 5 is what lets step 3 run migrations at
     * all — see the note on InstallService.
     */
    public function storeDatabase(DatabaseRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        try {
            $connection = $this->installer->connectToServer($credentials);
            $this->installer->ensureDatabaseExists($connection, $credentials['db_database']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['db_host' => $e->getMessage()]);
        }

        $this->installer->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $credentials['db_host'],
            'DB_PORT' => (string) $credentials['db_port'],
            'DB_DATABASE' => $credentials['db_database'],
            'DB_USERNAME' => $credentials['db_username'],
            'DB_PASSWORD' => (string) ($credentials['db_password'] ?? ''),
        ]);

        $this->installer->useConnection($credentials);
        $request->session()->put(UseInstallConnection::SESSION_KEY, $credentials);

        return redirect()->route('install.migrate');
    }

    public function migrate(): View
    {
        return view('install.migrate', [
            'step' => 3,
            'alreadyRun' => $this->tablesExist(),
        ]);
    }

    public function runMigrate(): RedirectResponse
    {
        try {
            $output = $this->installer->migrateAndSeed();
        } catch (Throwable $e) {
            return back()->withErrors(['migrate' => 'فشل إنشاء الجداول: ' . $e->getMessage()]);
        }

        return redirect()->route('install.admin')->with('migrate_output', $output);
    }

    public function admin(Request $request): View|RedirectResponse
    {
        if (! $this->tablesExist()) {
            return redirect()->route('install.migrate')
                ->withErrors(['migrate' => 'لازم تنشئ الجداول الأول.']);
        }

        return view('install.admin', [
            'step' => 4,
            'output' => $request->session()->get('migrate_output'),
        ]);
    }

    public function storeAdmin(AdminRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $roleId = Role::where('key', 'admin')->value('id');

        if ($roleId === null) {
            return back()->withInput()
                ->withErrors(['email' => 'دور مدير النظام مش موجود. شغّل خطوة إنشاء الجداول تاني.']);
        }

        $admin = $this->installer->createAdmin($request->validated(), $roleId);

        $logger->log(
            action: 'install.admin_created',
            subject: $admin,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return redirect()->route('install.finish');
    }

    public function finish(): View|RedirectResponse
    {
        if (! $this->adminExists()) {
            return redirect()->route('install.admin');
        }

        return view('install.finish', ['step' => 5]);
    }

    /** Step 5: fresh APP_KEY, debug off, lock file. From here /install is 404. */
    public function complete(Request $request): RedirectResponse
    {
        if (! $this->adminExists()) {
            return redirect()->route('install.admin');
        }

        try {
            $this->installer->finalize();
        } catch (RuntimeException $e) {
            return back()->withErrors(['finish' => $e->getMessage()]);
        }

        // The credentials have served their purpose; don't leave a database
        // password sitting in a session file.
        $request->session()->forget(UseInstallConnection::SESSION_KEY);

        return redirect()->route('login');
    }

    private function tablesExist(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('roles');
        } catch (Throwable) {
            return false;
        }
    }

    private function adminExists(): bool
    {
        try {
            return DB::table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
