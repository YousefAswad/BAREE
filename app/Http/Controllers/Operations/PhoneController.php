<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\PhoneLink;
use App\Services\WhatsApp\PhoneLinkService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class PhoneController extends Controller
{
    public function start(Request $request, PhoneLinkService $links): RedirectResponse
    {
        abort_unless(config('whatsapp.enabled') && $request->user()->tenant?->setting('whatsapp.enabled', false), 404);
        $phone = $request->validate(['phone' => ['required', 'string', 'max:24']])['phone'];

        try {
            $links->start($request->user(), $phone);
        } catch (DomainException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['phone' => __('Could not send the code right now.')]);
        }

        return back()->with('status', __('We sent a code to your WhatsApp.'));
    }

    public function verify(Request $request, PhoneLinkService $links): RedirectResponse
    {
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];

        try {
            $links->verify($request->user(), $code);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', __('WhatsApp is connected.'));
    }

    public function optOut(Request $request, PhoneLinkService $links): RedirectResponse
    {
        if ($link = PhoneLink::query()->where('user_id', $request->user()->id)->first()) {
            $links->optOut($link);
        }

        return back();
    }
}
