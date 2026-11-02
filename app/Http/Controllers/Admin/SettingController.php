<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Support\ActivityLogger;
use App\Support\ExportFormat;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'school_name' => Settings::get('school_name'),
                'allow_self_validation' => (bool) Settings::get('allow_self_validation'),
                'require_cross_validator_for_self' => (bool) Settings::get('require_cross_validator_for_self'),
                'export_format' => ExportFormat::get(),
            ],
            'fonts' => ExportFormat::fonts(),
            'pdf_enabled' => is_string(config('banksoal.soffice_path')) && config('banksoal.soffice_path') !== '',
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            Settings::set($key, $value);
        }

        ActivityLogger::log('settings.updated', null, $request->validated());

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
