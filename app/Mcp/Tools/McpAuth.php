<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use RuntimeException;

/** Every MCP call must come from a personal token with the "mcp" ability; tenant context is set from that user. */
trait McpAuth
{
    protected function mcpUser(): User
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->tokenCan('mcp') || $user->status !== 'active') {
            throw new RuntimeException('Unauthorised.');
        }
        app(TenantContext::class)->set($user->tenant_id);

        return $user;
    }
}
