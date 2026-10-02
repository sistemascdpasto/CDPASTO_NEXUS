<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Registra los sistemas desplegados en Railway (proyecto blissful-prosperity).
     * Idempotente: se puede correr en cada despliegue.
     *
     * Cada app Laravel queda vinculada a su servicio MySQL de Railway (las credenciales se leen en vivo
     * desde la API de Railway, no se guardan aquí).
     *
     * Las apps Laravel arrancan revisando /up (ruta de salud nativa de Laravel); cuando
     * se instale el agente Nexus se deja la ruta vacía para usar /nexus/health.
     */
    public function run(): void
    {
        $apps = [
            [
                'name' => 'Sistema de Gestión Adenar',
                'slug' => 'adenar',
                'type' => Application::TYPE_LARAVEL,
                'url' => 'https://sistemagestionadenar.up.railway.app',
                'internal_url' => 'http://cdpastosistemagestionadenar.railway.internal:8080',
                'health_path' => '/up',
                'railway_service_id' => 'bb39fe3e-6b76-4022-bb15-8512d6c2fdfa',
                'railway_service_name' => 'CDPASTO_SistemaGestionAdenar',
                'database_service_id' => 'a05da19a-c8d5-4672-962d-5af685ac2b04',
                'database_service_name' => 'MySQL',
                'repository' => 'sistemascdpasto/CDPASTO_SistemaGestionAdenar',
            ],
            [
                'name' => 'Sistema de Gestión EasyOL',
                'slug' => 'easyol',
                'type' => Application::TYPE_LARAVEL,
                'url' => 'https://sistemagestioneasy.up.railway.app',
                'internal_url' => 'http://cdpastosistemagestioneasyol.railway.internal:8080',
                'health_path' => '/up',
                'railway_service_id' => '31f7a028-3610-443b-bf21-424cd3d70ac1',
                'railway_service_name' => 'CDPASTO_SistemaGestionEasyOL',
                'database_service_id' => '0862bd9b-a251-4b47-a272-875dd9257bbd',
                'database_service_name' => 'MySQL-1_L8',
                'repository' => 'sistemascdpasto/CDPASTO_SistemaGestionEasyOL',
            ],
            [
                'name' => 'Sistema de Tickets',
                'slug' => 'tickets',
                'type' => Application::TYPE_LARAVEL,
                'url' => 'https://sistematicketscdpasto.up.railway.app',
                'internal_url' => 'http://cdpastosistematickets.railway.internal:8080',
                'health_path' => '/up',
                'railway_service_id' => 'f4b15c96-d184-42c6-9d3c-a515d5d41404',
                'railway_service_name' => 'CDPASTO_SIstemaTickets',
                'database_service_id' => '131e1cd7-5e7a-4f04-b5f9-fc254d5a39c9',
                'database_service_name' => 'MySQL-lwyK',
                'repository' => 'sistemascdpasto/CDPASTO_SIstemaTickets',
            ],
            [
                'name' => 'Software 5S',
                'slug' => 'software-5s',
                'type' => Application::TYPE_LARAVEL,
                'url' => 'https://cdpasto5s.up.railway.app',
                'internal_url' => 'http://cdpastosoftware5s.railway.internal:8080',
                'health_path' => '/up',
                'railway_service_id' => 'e66d7bcd-1bf7-46c4-aac3-8e252bb8c202',
                'railway_service_name' => 'CDPASTO_Software5s',
                'database_service_id' => '6c74b2e6-dd4c-44d8-abd9-143050c4a641',
                'database_service_name' => 'MySQL-8U3R',
                'repository' => 'sistemascdpasto/CDPASTO_Software5s',
            ],
            [
                'name' => 'Reempaque',
                'slug' => 'reempaque',
                'type' => Application::TYPE_STATIC,
                'url' => 'https://reempaquecdpasto.up.railway.app',
                'internal_url' => 'http://cdpastoreempaque.railway.internal:8080',
                'railway_service_id' => 'b013d308-9845-4472-982f-0dbe7f428df1',
                'railway_service_name' => 'CDPASTO_Reempaque',
                'check_interval_minutes' => 5,
            ],
            [
                'name' => 'TAT SIDER',
                'slug' => 'tat-sider',
                'type' => Application::TYPE_STATIC,
                'url' => 'https://tatsidercdpasto.up.railway.app',
                'internal_url' => 'http://cdpastotat-sider.railway.internal:8080',
                'railway_service_id' => '27fafc00-a91d-4fbf-a0fe-b54cda5c2130',
                'railway_service_name' => 'CDPASTO_TAT-SIDER',
                'check_interval_minutes' => 5,
            ],
        ];

        foreach ($apps as $data) {
            $app = Application::firstOrNew(['slug' => $data['slug']]);

            if ($app->exists) {
                // Completa la base de datos vinculada en apps registradas antes de esta función.
                if (! $app->database_service_id && isset($data['database_service_id'])) {
                    $app->fill(['database_service_id' => $data['database_service_id'], 'database_service_name' => $data['database_service_name']])->save();
                }

                if (! $app->internal_url && isset($data['internal_url'])) {
                    $app->fill(['internal_url' => $data['internal_url']])->save();
                }

                continue;
            }

            $app->fill($data);
            $app->regenerateApiKey();
            $app->save();

            $this->command?->info("App registrada: {$app->name} (genera su API key desde el panel)");
        }

        if (app()->environment('local')) {
            $this->call(TestUsersSeeder::class);
        }
    }
}
