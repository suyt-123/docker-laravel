<?php

namespace App\Http\Middleware;

use App\Auth\CapabilityAuthorizer;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $settings = app(SettingService::class)->nested();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'capabilities' => fn () => $user
                    ? app(CapabilityAuthorizer::class)->capabilityCodes($user)->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'features' => [
                'progressPhotos' => (bool) config('features.progress_photos'),
            ],
            'settings' => [
                ...$settings,
                'attendance' => [
                    ...($settings['attendance'] ?? []),
                    'requirePhoto' => (bool) ($settings['attendance']['require_photo'] ?? false),
                    'allowedDistanceMeters' => (int) ($settings['attendance']['allowed_distance_meters'] ?? 250),
                    'allowManualCorrection' => (bool) ($settings['attendance']['allow_manual_correction'] ?? false),
                ],
            ],
        ];
    }
}
