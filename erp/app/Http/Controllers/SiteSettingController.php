<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SiteSettingController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view company setting', only: ['index']),
            new Middleware('permission:edit company setting', only: ['update']),
        ];
    }

    public function index()
    {
        $settings = SiteSetting::query()->get()->keyBy('key');

        $siteSettings = [
            'app_name' => $settings->get('app_name')?->value ?? config('app.name'),
            'logo'     => $settings->get('logo')?->value,
            'favicon'  => $settings->get('favicon')?->value,
        ];

        return view('site-settings.index', compact('siteSettings'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => 'required|string|max:255',
            'logo'     => 'nullable|file|mimes:png,jpg,jpeg,webp,svg|max:4096',
            'favicon'  => 'nullable|file|mimes:png,jpg,jpeg,webp,svg,ico|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $this->saveSetting('app_name', $request->app_name, 'string');

        if ($request->hasFile('logo')) {
            $this->saveFileSetting('logo', $request->file('logo'));
        }

        if ($request->hasFile('favicon')) {
            $this->saveFileSetting('favicon', $request->file('favicon'));
        }

        Cache::forget('site_settings_map');
        config(['app.name' => $request->app_name]);

        return redirect()->route('role.site-settings.index', ['role' => request()->route('role')])
            ->with('success', 'Site settings updated successfully.');
    }

    protected function saveSetting(string $key, ?string $value, string $type = 'string'): void
    {
        SiteSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    protected function saveFileSetting(string $key, \Illuminate\Http\UploadedFile $file): void
    {
        $setting = SiteSetting::firstOrNew(['key' => $key]);

        if (!empty($setting->value) && !str_starts_with($setting->value, 'http')) {
            $oldFile = public_path($setting->value);
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $uploadPath = 'image/site-settings/';
        if (!file_exists(public_path($uploadPath))) {
            mkdir(public_path($uploadPath), 0777, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $key . '_' . uniqid() . '.' . $extension;
        $file->move(public_path($uploadPath), $filename);

        $setting->key = $key;
        $setting->value = $uploadPath . $filename;
        $setting->type = 'file';
        $setting->save();
    }
}