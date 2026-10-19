<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Support\OrganizationContext;

trait SharesLookups
{
    protected function branchOptions(): mixed
    {
        return Branch::query()->orderBy('name')->get(['id', 'name']);
    }

    protected function customerOptions(): mixed
    {
        return Customer::query()->orderBy('name')->get(['id', 'name']);
    }

    protected function serviceOptions(): mixed
    {
        return Service::query()->orderBy('name')->get(['id', 'name']);
    }

    protected function technicianOptions(): mixed
    {
        return Technician::query()->with('user:id,name')->orderBy('employee_code')->get()->map(fn (Technician $technician) => [
            'id' => $technician->id,
            'name' => $technician->user?->name,
            'employee_code' => $technician->employee_code,
        ]);
    }

    protected function requestOptions(): mixed
    {
        return ServiceRequest::query()->latest('requested_at')->limit(100)->get(['id', 'subject', 'customer_id']);
    }

    protected function memberOptions(): mixed
    {
        return User::query()
            ->whereIn('id', OrganizationContext::current()->organization->members()->pluck('users.id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
