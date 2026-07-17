<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\SystemSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use RuntimeException;

/**
 * The install wizard's engine (F00.1 / PLAN.md § 9).
 *
 * On write order: PLAN § 9 lists ".env + APP_KEY" as step 5, but migrations
 * (step 3) cannot run before the connection details are on disk, and Laravel
 * will not boot at all without an APP_KEY. So the file is filled in as each
 * step earns it, and step 5 does the final hardening. The five screens the
 * user walks through are unchanged.
 */
class InstallService
{
    private const REQUIRED_PHP = '8.2.0';

    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql' => 'الاتصال بقاعدة البيانات',
        'mbstring' => 'معالجة النصوص العربية',
        'fileinfo' => 'التحقق من نوع الملفات المرفوعة',
        'gd' => 'إعادة معالجة الصور وتوليد المصغّرات',
        'openssl' => 'التشفير والجلسات',
        'zip' => 'استيراد وتصدير ملفات Excel',
    ];

    /**
     * @return array{passed: bool, checks: array<int, array{name: string, hint: string, value: string, ok: bool}>}
     */
    public function checkRequirements(): array
    {
        $checks = [[
            'name' => 'إصدار PHP',
            'hint' => 'المطلوب ' . self::REQUIRED_PHP . ' أو أحدث',
            'value' => PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, self::REQUIRED_PHP, '>='),
        ]];

        foreach (self::REQUIRED_EXTENSIONS as $extension => $hint) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'name' => "إكستنشن {$extension}",
                'hint' => $hint,
                'value' => $loaded ? 'مثبّت' : 'غير مثبّت',
                'ok' => $loaded,
            ];
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $writable = is_writable($path);
            $checks[] = [
                'name' => "الكتابة على {$label}/",
                'hint' => 'المجلد لازم يكون قابل للكتابة',
                'value' => $writable ? 'مسموح' : 'ممنوع',
                'ok' => $writable,
            ];
        }

        return [
            'passed' => ! in_array(false, array_column($checks, 'ok'), true),
            'checks' => $checks,
        ];
    }

    /**
     * Opens a server-level connection (no database selected) so we can both
     * verify the credentials and create the schema if it isn't there yet.
     *
     * @throws RuntimeException with a message meant for a human, never a stack trace.
     */
    public function connectToServer(array $credentials): PDO
    {
        $dsn = "mysql:host={$credentials['db_host']};port={$credentials['db_port']}";

        try {
            return new PDO($dsn, $credentials['db_username'], $credentials['db_password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException($this->humanConnectionError($e));
        }
    }

    /** F00.1: entering a database name that doesn't exist must create it. */
    public function ensureDatabaseExists(PDO $connection, string $database): void
    {
        // A schema name can't be bound as a parameter, so it is whitelisted by
        // the Form Request (letters, digits, underscore) and quoted here.
        // This is the one place CLAUDE.md § 5's "no raw SQL" cannot apply.
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new RuntimeException('اسم قاعدة البيانات لازم يكون حروف إنجليزية وأرقام و _ بس.');
        }

        try {
            $connection->exec(
                "CREATE DATABASE IF NOT EXISTS `{$database}` "
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                "مفيش صلاحية لإنشاء قاعدة بيانات باسم «{$database}». "
                . 'اعملها يدوياً أو استخدم مستخدم له صلاحية CREATE.'
            );
        }
    }

    /**
     * Points the live connection at the new database for the rest of this
     * request, so the next step boots with .env already correct.
     */
    public function useConnection(array $credentials): void
    {
        Config::set('database.connections.mysql', array_merge(
            config('database.connections.mysql'),
            [
                'host' => $credentials['db_host'],
                'port' => $credentials['db_port'],
                'database' => $credentials['db_database'],
                'username' => $credentials['db_username'],
                'password' => $credentials['db_password'] ?? '',
            ]
        ));

        DB::purge('mysql');
    }

    /** Step 3: schema + the rows a real installation needs. No demo data. */
    public function migrateAndSeed(): string
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        Artisan::call('db:seed', ['--class' => SystemSeeder::class, '--force' => true]);
        $output .= PHP_EOL . Artisan::output();

        // Settings and the role/permission map are cached forever and busted by
        // model events — which seeding a brand-new database never fires. Re-running
        // the wizard over a warm cache would otherwise serve the old install's
        // settings.
        Artisan::call('cache:clear');

        return trim($output);
    }

    /** Step 4. The first admin — the only user the installer ever creates. */
    public function createAdmin(array $data, int $adminRoleId): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $adminRoleId,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /**
     * Step 5. A fresh key per installation, debug off, and the lock file that
     * makes /install answer 404 from here on (F00.1).
     */
    public function finalize(): void
    {
        $this->writeEnv([
            'APP_KEY' => 'base64:' . base64_encode(Encrypter::generateKey(config('app.cipher'))),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);

        Artisan::call('config:clear');

        if (file_put_contents($this->lockPath(), now()->toIso8601String()) === false) {
            throw new RuntimeException('مقدرتش أكتب ملف storage/installed.lock — راجع صلاحيات مجلد storage.');
        }
    }

    /** @param array<string, string> $values */
    public function writeEnv(array $values): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            if (! copy(base_path('.env.example'), $path)) {
                throw new RuntimeException('مقدرتش أعمل ملف .env — راجع صلاحيات مجلد المشروع.');
            }
        }

        $content = file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key . '=' . $this->quote((string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            // Callback, not a replacement string: a password containing $ or \
            // would otherwise be read as a backreference.
            $content = preg_match($pattern, $content) === 1
                ? preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content, "\r\n") . PHP_EOL . $line . PHP_EOL;
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('مقدرتش أكتب ملف .env — راجع صلاحيات مجلد المشروع.');
        }
    }

    public function lockPath(): string
    {
        return storage_path('installed.lock');
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s"\'#$&\\\\]/', $value) === 1) {
            return '"' . addcslashes($value, '\\"$') . '"';
        }

        return $value;
    }

    /** Turns a PDO driver error into something an installer can act on. */
    private function humanConnectionError(PDOException $e): string
    {
        return match ((int) $e->getCode()) {
            1045 => 'اسم المستخدم أو كلمة السر غلط.',
            2002 => 'مفيش سيرفر MySQL على العنوان والبورت دول. اتأكد إنه شغال.',
            1049 => 'قاعدة البيانات دي مش موجودة.',
            default => 'مقدرتش أتصل بقاعدة البيانات: ' . $e->getMessage(),
        };
    }
}
