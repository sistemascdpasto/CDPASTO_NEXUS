<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Application;
use App\Services\PanelAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anuncios (banners) que se muestran al iniciar sesión en las apps y en la web principal.
 * Solo superadmin (middleware can:superadmin en la ruta).
 */
class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $since = now()->subDays(30)->toDateString();

        $announcements = Announcement::query()
            ->with('applications:id,name,slug')
            ->withSum(['stats as views_total' => fn ($q) => $q], 'views')
            ->withSum(['stats as clicks_total' => fn ($q) => $q], 'clicks')
            ->withSum(['stats as views_30d' => fn ($q) => $q->where('date', '>=', $since)], 'views')
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->latest('id')
            ->get()
            ->map(fn (Announcement $a) => $this->present($a) + [
                'views_total' => (int) $a->views_total,
                'clicks_total' => (int) $a->clicks_total,
                'views_30d' => (int) $a->views_30d,
            ]);

        return Inertia::render('announcements/index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('announcements/form', [
            'announcement' => null,
            'applications' => $this->targetApplications(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $announcement = DB::transaction(function () use ($request, $data) {
            $announcement = new Announcement($data);
            $announcement->created_by = $request->user()->id;
            $announcement->setImage($request->file('image')->get(), $request->file('image')->getMimeType());
            $announcement->save();
            $announcement->applications()->sync($data['application_ids']);

            return $announcement;
        });

        PanelAudit::log('announcement.created', "Creó el anuncio «{$announcement->title}»", $announcement, [
            'apps' => $announcement->applications()->pluck('name'),
        ]);

        return redirect()->route('announcements.index')->with('success', 'Anuncio creado. Las apps lo reciben en el próximo minuto.');
    }

    public function edit(Announcement $announcement): Response
    {
        return Inertia::render('announcements/form', [
            'announcement' => $this->present($announcement->load('applications:id,name,slug')),
            'applications' => $this->targetApplications(),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request, $announcement);

        DB::transaction(function () use ($request, $announcement, $data) {
            $announcement->fill($data);

            if ($request->hasFile('image')) {
                $announcement->setImage($request->file('image')->get(), $request->file('image')->getMimeType());
            }

            $announcement->save();
            $announcement->applications()->sync($data['application_ids']);
        });

        PanelAudit::log('announcement.updated', "Editó el anuncio «{$announcement->title}»", $announcement);

        return redirect()->route('announcements.index')->with('success', 'Anuncio actualizado.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        PanelAudit::log('announcement.toggled', ($announcement->is_active ? 'Activó' : 'Pausó')." el anuncio «{$announcement->title}»", $announcement);

        return back()->with('success', $announcement->is_active ? 'Anuncio activado.' : 'Anuncio pausado.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        PanelAudit::log('announcement.deleted', "Eliminó el anuncio «{$announcement->title}»", null, ['uuid' => $announcement->uuid]);
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Anuncio eliminado.');
    }

    /**
     * Apps que pueden mostrar anuncios: las que tienen login (agente) y las webs configuradas.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function targetApplications()
    {
        return Application::query()
            ->active()
            ->where(fn (Builder $q) => $q->where('type', Application::TYPE_LARAVEL)->orWhereIn('slug', config('nexus.announcements.web_apps', [])))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'url'])
            ->map(fn (Application $app) => [
                ...$app->only(['id', 'name', 'slug', 'url']),
                'kind' => $app->type === Application::TYPE_LARAVEL ? 'login' : 'web',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Announcement $announcement): array
    {
        $allowed = $this->targetApplications()->pluck('id')->all();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'image' => [$announcement ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'link_url' => ['nullable', 'url:http,https', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:60'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', Rule::when($request->filled('starts_at'), 'after:starts_at')],
            'is_active' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['integer', Rule::in($allowed)],
        ], [
            'application_ids.required' => 'Elige al menos una app donde mostrar el anuncio.',
            'image.max' => 'La imagen no puede pesar más de 5 MB.',
            'ends_at.after' => 'La fecha de fin debe ser posterior a la de inicio.',
        ]);

        unset($data['image']);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Announcement $a): array
    {
        return [
            ...$a->only(['id', 'uuid', 'title', 'link_url', 'link_label', 'starts_at', 'ends_at', 'is_active', 'priority', 'image_width', 'image_height', 'image_size', 'created_at', 'updated_at']),
            'image_url' => $a->imageUrl(),
            'state' => $a->state(),
            'applications' => $a->applications->map->only(['id', 'name', 'slug'])->values(),
        ];
    }
}
