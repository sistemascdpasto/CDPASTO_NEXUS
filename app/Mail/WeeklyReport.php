<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Models\Incident;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\HealthScore;
use App\Services\Metrics;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Resumen semanal de las apps que el usuario tiene a cargo.
 */
class WeeklyReport extends Mailable
{
    use Queueable, SerializesModels;

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public string $period;

    public function __construct(public User $user)
    {
        $from = now()->subWeek()->startOfDay();
        $this->period = $from->translatedFormat('j \d\e F').' al '.now()->translatedFormat('j \d\e F');

        /** @var Collection<int, Application> $apps */
        $apps = Application::visibleTo($user)->active()->orderBy('name')->get();
        $ids = $apps->pluck('id')->all();
        $uptime = app(Metrics::class)->uptimeByApp($from, $ids);
        $scores = app(HealthScore::class)->forApplications($apps);

        $incidents = Incident::whereIn('application_id', $ids)->where('started_at', '>=', $from)->get()->groupBy('application_id');
        $errors = ErrorEvent::whereIn('application_id', $ids)->where('occurred_at', '>=', $from)->groupBy('application_id')->selectRaw('application_id, COUNT(*) as total')->pluck('total', 'application_id');
        $actions = AuditLog::whereIn('application_id', $ids)->where('occurred_at', '>=', $from)->groupBy('application_id', 'action')->selectRaw('application_id, action, COUNT(*) as total')->get()->groupBy('application_id');
        $users = UserActivity::whereIn('application_id', $ids)->where('bucket', '>=', $from)->groupBy('application_id')->selectRaw('application_id, COUNT(DISTINCT external_user_id) as total')->pluck('total', 'application_id');

        $this->rows = $apps->map(fn (Application $app) => [
            'name' => $app->name,
            'url' => route('applications.show', $app),
            'score' => $scores[$app->id]['score'] ?? null,
            'uptime' => $uptime[$app->id] ?? null,
            'incidents' => $incidents->get($app->id, collect())->count(),
            'downtime_minutes' => (int) round($incidents->get($app->id, collect())->sum(fn (Incident $i) => $i->currentDuration()) / 60),
            'errors' => (int) ($errors[$app->id] ?? 0),
            'users' => (int) ($users[$app->id] ?? 0),
            'created' => (int) ($actions->get($app->id, collect())->firstWhere('action', 'created')->total ?? 0),
            'updated' => (int) ($actions->get($app->id, collect())->firstWhere('action', 'updated')->total ?? 0),
            'deleted' => (int) ($actions->get($app->id, collect())->firstWhere('action', 'deleted')->total ?? 0),
            'uploaded' => (int) ($actions->get($app->id, collect())->firstWhere('action', 'uploaded')->total ?? 0),
        ])->all();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Nexus] Resumen semanal · {$this->period}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.weekly-report');
    }
}
