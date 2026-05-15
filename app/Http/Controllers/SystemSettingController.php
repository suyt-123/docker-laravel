<?php

namespace App\Http\Controllers;

use App\Services\SettingService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('SystemSettings/Edit', [
            'groups' => $this->settings->grouped(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $definitions = $this->settings->definitions();
        $rules = [];

        foreach ($definitions as $key => $definition) {
            $field = "settings.{$key}";
            $rules[$field] = match ($definition['type']) {
                'boolean' => ['nullable', 'boolean'],
                'integer' => ['required', 'integer', 'min:'.($definition['min'] ?? 0)],
                'text' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $validated = $request->validate($rules);
        $values = Arr::dot($validated['settings'] ?? []);
        $oldValues = collect(array_keys($definitions))
            ->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])
            ->all();

        $this->settings->updateMany($values, null, $request->user()?->id);

        app(ActivityLogger::class)->log(
            'update',
            'system_settings.updated',
            null,
            $oldValues,
            collect(array_keys($definitions))
                ->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])
                ->all(),
            '系統設定已更新',
            'system_settings',
        );

        return redirect()
            ->route('system-settings.edit')
            ->with('success', '系統設定已更新。');
    }
}
