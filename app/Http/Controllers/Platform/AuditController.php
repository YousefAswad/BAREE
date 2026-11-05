<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('platform.audit.view');

        return Inertia::render('Platform/Audit/Index', [
            'logs' => AuditLog::query()
                ->when($request->input('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
                ->when($request->input('action'), fn ($q, $a) => $q->where('action', 'like', addcslashes($a, '%_').'%'))
                ->orderByDesc('id')->paginate(50)->withQueryString(),
            'filters' => $request->only(['tenant_id', 'action']),
        ]);
    }
}
