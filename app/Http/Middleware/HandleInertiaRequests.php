<?php

namespace App\Http\Middleware;

use App\Models\Alert;
use App\Models\Application;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'is_superadmin' => $user->isSuperadmin(),
                    'two_factor' => $user->hasTwoFactorEnabled(),
                ] : null,
            ],
            'openAlerts' => fn () => $user
                ? Alert::visibleTo($user)->whereNull('acknowledged_at')->where('severity', '!=', 'info')->count()
                : 0,
            // Pulso general para el encabezado: cuántas apps visibles están caídas o degradadas.
            'systemPulse' => fn () => $user
                ? Application::visibleTo($user)->active()->toBase()
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END) as down")
                    ->selectRaw("SUM(CASE WHEN status = 'degraded' THEN 1 ELSE 0 END) as degraded")
                    ->first()
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
