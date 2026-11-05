<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\PhoneLink;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->input('type');

        return Inertia::render('Notifications/Index', [
            'notifications' => $request->user()->notifications()
                ->when($type, fn ($q) => $q->where('data->type', $type))
                ->limit(100)->get(['id', 'data', 'read_at', 'created_at']),
            'unread' => $request->user()->unreadNotifications()->count(),
            'filter' => $type,
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function preferences(Request $request, NotificationPreferences $prefs): Response
    {
        $link = PhoneLink::query()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Account/Notifications', [
            'matrix' => $prefs->matrix($request->user()),
            'whatsapp' => ['available' => (bool) config('whatsapp.enabled') && (bool) $request->user()->tenant?->setting('whatsapp.enabled', false),
                'linked' => (bool) $link?->verified_at, 'receiving' => (bool) $link?->canReceive(), 'pending' => $link && ! $link->verified_at],
        ]);
    }

    public function updatePreferences(Request $request, NotificationPreferences $prefs): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:'.implode(',', NotificationPreferences::TYPES)], 'channel' => ['required', 'in:database,mail,whatsapp'], 'enabled' => ['required', 'boolean']]);
        $prefs->set($request->user(), $data['type'], $data['channel'], $data['enabled']);

        return back();
    }
}
