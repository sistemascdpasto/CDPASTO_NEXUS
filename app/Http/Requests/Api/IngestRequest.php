<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_version' => ['nullable', 'string', 'max:20'],

            'requests' => ['sometimes', 'array', 'max:5000'],
            'requests.*.bucket' => ['required', 'date'],
            'requests.*.method' => ['required', 'string', 'max:10'],
            'requests.*.route' => ['required', 'string'],
            'requests.*.count' => ['required', 'integer', 'min:1'],
            'requests.*.total_ms' => ['required', 'integer', 'min:0'],
            'requests.*.max_ms' => ['required', 'integer', 'min:0'],
            'requests.*.errors_4xx' => ['nullable', 'integer', 'min:0'],
            'requests.*.errors_5xx' => ['nullable', 'integer', 'min:0'],

            'activity' => ['sometimes', 'array', 'max:5000'],
            'activity.*.bucket' => ['required', 'date'],
            'activity.*.user_id' => ['required'],
            'activity.*.user_name' => ['nullable', 'string', 'max:255'],
            'activity.*.requests' => ['required', 'integer', 'min:1'],
            'activity.*.routes' => ['nullable', 'array'],

            'exceptions' => ['sometimes', 'array', 'max:3000'],
            'exceptions.*.class' => ['required', 'string'],
            'exceptions.*.message' => ['nullable', 'string'],
            'exceptions.*.file' => ['nullable', 'string'],
            'exceptions.*.line' => ['nullable', 'integer'],
            'exceptions.*.trace' => ['nullable', 'string'],
            'exceptions.*.url' => ['nullable', 'string'],
            'exceptions.*.method' => ['nullable', 'string', 'max:10'],
            'exceptions.*.ip' => ['nullable', 'string', 'max:45'],
            'exceptions.*.user_id' => ['nullable'],
            'exceptions.*.user_name' => ['nullable', 'string', 'max:255'],
            'exceptions.*.occurred_at' => ['required', 'date'],

            'logins' => ['sometimes', 'array', 'max:3000'],
            'logins.*.event' => ['required', 'in:login,failed,logout,lockout,blocked'],
            'logins.*.user_id' => ['nullable'],
            'logins.*.identifier' => ['nullable', 'string'],
            'logins.*.user_name' => ['nullable', 'string', 'max:255'],
            'logins.*.ip' => ['nullable', 'string', 'max:45'],
            'logins.*.user_agent' => ['nullable', 'string'],
            'logins.*.occurred_at' => ['required', 'date'],

            'audits' => ['sometimes', 'array', 'max:5000'],
            'audits.*.action' => ['required', 'string', 'max:30'],
            'audits.*.module' => ['required', 'string'],
            'audits.*.record_id' => ['nullable'],
            'audits.*.old' => ['nullable', 'array'],
            'audits.*.new' => ['nullable', 'array'],
            'audits.*.user_id' => ['nullable'],
            'audits.*.user_name' => ['nullable', 'string', 'max:255'],
            'audits.*.ip' => ['nullable', 'string', 'max:45'],
            'audits.*.url' => ['nullable', 'string'],
            'audits.*.occurred_at' => ['required', 'date'],

            // Si viene la clave, reemplaza la fotografía de sesiones activas (aunque sea vacía).
            'sessions' => ['sometimes', 'nullable', 'array', 'max:5000'],
            'sessions.*.user_id' => ['required'],
            'sessions.*.user_name' => ['nullable', 'string', 'max:255'],
            'sessions.*.user_email' => ['nullable', 'string', 'max:255'],
            'sessions.*.user_role' => ['nullable', 'string', 'max:255'],
            'sessions.*.ip' => ['nullable', 'string', 'max:45'],
            'sessions.*.user_agent' => ['nullable', 'string'],
            'sessions.*.last_activity' => ['required', 'date'],
        ];
    }
}
