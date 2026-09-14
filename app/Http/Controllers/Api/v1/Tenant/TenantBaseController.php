<?php

namespace App\Http\Controllers\Api\v1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class TenantBaseController extends Controller
{
    /**
     * Retrieve the tenant profile for the authenticated user.
     */
    protected function getTenant(Request $request): ?Tenant
    {
        return $request->user()->tenant()->first();
    }

    /**
     * Retrieve tenant or abort with 404 if profile does not exist.
     */
    protected function requireTenant(Request $request): Tenant
    {
        $tenant = $this->getTenant($request);

        if (! $tenant) {
            abort(404, 'No tenant profile associated with this account.');
        }

        return $tenant;
    }
}
