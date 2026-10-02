<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;
use App\Services\PanelAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de los usuarios del panel: jefes y encargados de cada sistema.
 * Solo superadmin (middleware can:superadmin en la ruta).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        return Inertia::render('users/index', [
            'users' => User::query()
                ->with('applications:id,name')
                ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    ...$user->only(['id', 'name', 'email', 'phone', 'is_active', 'receive_alerts', 'last_login_at', 'last_login_ip', 'created_at']),
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'two_factor' => $user->hasTwoFactorEnabled(),
                    'applications' => $user->applications,
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->applications()->sync($data['role'] === UserRole::Superadmin->value ? [] : $data['application_ids'] ?? []);

            return $user;
        });

        PanelAudit::log('user.created', "Creó el usuario {$user->name} ({$user->role->label()})", $user, [
            'applications' => $user->applications()->pluck('name'),
        ]);

        return redirect()->route('users.index')->with('success', "Usuario {$user->name} creado. Deberá configurar el doble factor en su primer ingreso.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== UserRole::Superadmin->value || ! $data['is_active'])) {
            return back()->with('error', 'No puedes quitarte el rol de superadministrador ni desactivar tu propia cuenta.');
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $before = $user->applications()->pluck('applications.id')->sort()->values()->all();

        $user->fill($data);
        $changes = array_keys($user->getDirty());
        $user->save();
        $user->applications()->sync($data['role'] === UserRole::Superadmin->value ? [] : $data['application_ids'] ?? []);

        $after = $user->applications()->pluck('applications.id')->sort()->values()->all();

        // Si se desactiva, se cierran sus sesiones del panel.
        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        PanelAudit::log('user.updated', "Editó el usuario {$user->name}", $user, [
            'changes' => array_values(array_diff($changes, ['password', 'updated_at'])),
            'password_changed' => in_array('password', $changes, true),
            'applications_before' => $before,
            'applications_after' => $after,
        ]);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        PanelAudit::log('user.deleted', "Eliminó el usuario {$user->name} ({$user->email})", null, ['id' => $user->id]);

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }

    /**
     * Para cuando un usuario pierde el teléfono: deberá configurar el 2FA de nuevo.
     */
    public function resetTwoFactor(User $user): RedirectResponse
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        PanelAudit::log('user.2fa_reset', "Reinició el doble factor de {$user->name}", $user);

        return back()->with('success', "Doble factor de {$user->name} reiniciado. Lo configurará en su próximo ingreso.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'receive_alerts' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'application_ids' => ['array'],
            'application_ids.*' => ['integer', 'exists:applications,id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?User $user): array
    {
        return [
            'user' => $user ? [
                ...$user->only(['id', 'name', 'email', 'phone', 'is_active', 'receive_alerts']),
                'role' => $user->role->value,
                'application_ids' => $user->applications()->pluck('applications.id'),
                'two_factor' => $user->hasTwoFactorEnabled(),
            ] : null,
            'roles' => UserRole::options(),
            'applications' => Application::orderBy('name')->get(['id', 'name', 'is_active']),
            'whatsappDriver' => config('nexus.whatsapp.driver'),
        ];
    }
}
