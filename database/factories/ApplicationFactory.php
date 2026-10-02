<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => Application::TYPE_LARAVEL,
            'url' => 'https://'.fake()->unique()->domainWord().'.up.railway.app',
            'environment' => 'production',
            'check_interval_minutes' => 1,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Application $app) => $app->api_key_hash ?? $app->regenerateApiKey());
    }

    public function static(): static
    {
        return $this->state(['type' => Application::TYPE_STATIC]);
    }
}
