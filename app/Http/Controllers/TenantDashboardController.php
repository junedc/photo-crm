<?php

namespace App\Http\Controllers;

use App\Tenancy\CurrentTenant;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant): View
    {
        return view('dashboard', [
            'tenant' => $currentTenant->get(),
        ]);
    }
}
