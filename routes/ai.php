<?php

// Published by `php artisan vendor:publish --tag=ai-routes` (laravel/mcp). Personal tokens with the "mcp" ability.

use App\Http\Middleware\SetTenantContext;
use App\Mcp\Servers\BareeServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/baree', BareeServer::class)->middleware(['auth:sanctum', SetTenantContext::class, 'throttle:ai']);
