<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('superadmin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $application = $this->route('application');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('applications')->ignore($application)],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in([Application::TYPE_LARAVEL, Application::TYPE_STATIC])],
            'url' => ['required', 'url:https,http', 'max:255'],
            'health_path' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', 'string', 'max:30'],
            'railway_service_id' => ['nullable', 'string', 'max:64'],
            'railway_service_name' => ['nullable', 'string', 'max:255'],
            'database_service_id' => ['nullable', 'string', 'max:64'],
            'database_service_name' => ['nullable', 'string', 'max:255'],
            'repository' => ['nullable', 'string', 'max:255'],
            'check_interval_minutes' => ['required', 'integer', Rule::in([1, 2, 5, 10, 15, 30, 60])],
            'slow_threshold_ms' => ['required', 'integer', 'min:200', 'max:60000'],
            'error_threshold' => ['required', 'integer', 'min:1', 'max:100000'],
            'failed_login_threshold' => ['required', 'integer', 'min:1', 'max:100000'],
            'mass_delete_threshold' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['boolean'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'url' => 'URL',
            'check_interval_minutes' => 'intervalo de verificación',
            'slow_threshold_ms' => 'umbral de lentitud',
            'error_threshold' => 'umbral de errores',
            'failed_login_threshold' => 'umbral de logins fallidos',
            'mass_delete_threshold' => 'umbral de eliminaciones',
        ];
    }
}
