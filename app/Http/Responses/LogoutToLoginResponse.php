<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/** After Fortify has invalidated the session and regenerated the CSRF token, send the user to the public login page. */
final class LogoutToLoginResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        \Inertia\Inertia::clearHistory(); // flashed onto the NEW session; the client then drops its history encryption key

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect('/login', 303);
    }
}
