<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Http\Support\Paginated;
use App\Models\AuditEvent;
use App\Support\Config\BmsSettings;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request, CurrentBarangay $currentBarangay, BmsSettings $settings): Response
    {
        $barangayId = $currentBarangay->id();

        $events = AuditEvent::query()
            ->with('actor:id,name')
            ->when($barangayId === null, fn ($query) => $query->whereNull('barangay_id'), fn ($query) => $query->where('barangay_id', $barangayId))
            ->latest('id')
            ->paginate($settings->pagination->clamp($request->integer('per_page')))
            ->withQueryString();

        return Inertia::render('admin/audit/Index', [
            'events' => Paginated::from($events, AuditEventResource::class),
        ]);
    }
}
