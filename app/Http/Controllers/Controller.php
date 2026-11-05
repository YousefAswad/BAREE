<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Replaces the starter kit's empty base controller: BAREE controllers call $this->authorize(),
 * which needs AuthorizesRequests (not included by default since Laravel 11).
 */
abstract class Controller
{
    use AuthorizesRequests;
}
