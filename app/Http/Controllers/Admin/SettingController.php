<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.index', [
            'values' => Setting::all_cached(),
            'priorities' => Priority::cases(),
            'weekDays' => $this->weekDays(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->safe()->except(['app_logo', 'remove_logo']);

        try {
            if ($request->boolean('remove_logo')) {
                $this->settings->removeLogo();
            }

            if ($request->hasFile('app_logo')) {
                $this->settings->storeLogo($request->file('app_logo'));
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['app_logo' => $e->getMessage()]);
        }

        $changes = $this->settings->update($data);

        if ($changes !== []) {
            $logger->log(
                action: 'settings.updated',
                userId: $request->user()->id,
                changes: $changes,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return back()->with('status', 'تم حفظ الإعدادات.');
    }

    /** Carbon day numbering: 0 = Sunday .. 6 = Saturday. */
    private function weekDays(): array
    {
        return [
            6 => 'السبت',
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
        ];
    }
}
