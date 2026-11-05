<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\UserImport;
use App\Services\Institution\UserImportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserImportController extends Controller
{
    public function store(Request $request, UserImportService $imports): RedirectResponse
    {
        $this->authorize('users.invite');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            $import = $imports->stage($request->file('file'), $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return to_route('admin.imports.show', $import);
    }

    public function show(UserImport $import): Response
    {
        $this->authorize('users.invite');

        return Inertia::render('Institution/Users/Import', ['import' => $import->only(['id', 'status', 'total', 'valid', 'invited', 'report'])]);
    }

    public function commit(Request $request, UserImport $import, UserImportService $imports): RedirectResponse
    {
        $this->authorize('users.invite');

        try {
            $imports->commit($import, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        return back()->with('status', __('Invitations are being sent.'));
    }
}
