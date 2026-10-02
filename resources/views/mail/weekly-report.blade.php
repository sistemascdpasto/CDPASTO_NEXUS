<x-mail::message>
# Resumen semanal de tus sistemas

Hola {{ $user->name }}, este es el estado de tus aplicaciones del **{{ $period }}**.

<x-mail::table>
| Sistema | Salud | Disponibilidad | Caídas | Errores | Usuarios |
|:--|:-:|:-:|:-:|:-:|:-:|
@foreach ($rows as $row)
| [{{ $row['name'] }}]({{ $row['url'] }}) | {{ $row['score'] ?? '—' }}/100 | {{ $row['uptime'] !== null ? number_format($row['uptime'], 2).'%' : '—' }} | {{ $row['incidents'] }}{{ $row['downtime_minutes'] ? ' ('.$row['downtime_minutes'].' min)' : '' }} | {{ $row['errors'] }} | {{ $row['users'] }} |
@endforeach
</x-mail::table>

## Lo que hicieron los usuarios

<x-mail::table>
| Sistema | Creados | Editados | Eliminados | Archivos subidos |
|:--|:-:|:-:|:-:|:-:|
@foreach ($rows as $row)
| {{ $row['name'] }} | {{ $row['created'] }} | {{ $row['updated'] }} | {{ $row['deleted'] }} | {{ $row['uploaded'] }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('dashboard')">
Abrir Nexus
</x-mail::button>

Nexus · Centro de control CD Pasto
</x-mail::message>
