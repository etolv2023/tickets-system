<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;
use finfo;

class SettingService
{
    private const LOGO_DIR = 'branding';

    private const LOGO_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /** Column types by key, so a value round-trips as the right PHP type. */
    private const TYPES = [
        'app_name' => 'string',
        'app_logo' => 'string',
        'daily_capacity_hours' => 'float',
        'week_start_day' => 'int',
        'work_days' => 'json',
        'work_day_start' => 'string',
        'work_day_end' => 'string',
        'email_notifications_enabled' => 'bool',
    ];

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{from: mixed, to: mixed}> what actually changed, for the audit log
     */
    public function update(array $values): array
    {
        $changes = [];

        foreach ($values as $key => $value) {
            $type = self::TYPES[$key] ?? 'string';
            $stored = $this->serialize($value, $type);

            $setting = Setting::firstOrNew(['key' => $key]);

            if ($setting->exists && $setting->value === $stored) {
                continue;
            }

            $changes[$key] = ['from' => $setting->value, 'to' => $stored];

            $setting->fill(['value' => $stored, 'type' => $type])->save();
        }

        return $changes;
    }

    /**
     * Re-encodes the upload rather than storing it. Whatever was hidden in the
     * original bytes does not survive a decode/encode round trip (CLAUDE.md § 5).
     *
     * @return string path on the public disk
     */
    public function storeLogo(UploadedFile $file): string
    {
        $this->assertRealImage($file);

        $encoded = ImageManager::gd()
            ->read($file->getRealPath())
            ->scaleDown(width: 400, height: 120)
            ->toPng();

        $path = self::LOGO_DIR . '/' . Str::uuid() . '.png';

        Storage::disk('public')->put($path, (string) $encoded);

        $this->deleteStoredLogo();
        $this->update(['app_logo' => $path]);

        return $path;
    }

    public function removeLogo(): void
    {
        $this->deleteStoredLogo();
        $this->update(['app_logo' => null]);
    }

    /**
     * The extension and the browser-supplied type are both attacker-controlled.
     * finfo reads the actual bytes — this is what stops shell.php renamed to .jpg.
     */
    private function assertRealImage(UploadedFile $file): void
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (! in_array($mime, self::LOGO_MIMES, true)) {
            throw new RuntimeException('الملف ده مش صورة صالحة. المسموح: PNG أو JPG أو WEBP.');
        }

        if (@getimagesize($file->getRealPath()) === false) {
            throw new RuntimeException('مقدرتش أقرا الصورة دي. جرّب ملف تاني.');
        }
    }

    private function deleteStoredLogo(): void
    {
        $current = Setting::get('app_logo');

        if (is_string($current) && $current !== '' && Storage::disk('public')->exists($current)) {
            Storage::disk('public')->delete($current);
        }
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode(array_values((array) $value)),
            default => (string) $value,
        };
    }
}
