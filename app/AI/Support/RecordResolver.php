<?php

namespace App\AI\Support;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RecordResolver
{
    public function customer(?string $id, ?string $name): Customer
    {
        return $this->one(Customer::class, $id, $name, 'name', 'customer');
    }

    public function service(?string $id, ?string $name): ?Service
    {
        if (($id === null || $id === '') && ($name === null || $name === '')) {
            return null;
        }

        return $this->one(Service::class, $id, $name, 'name', 'service');
    }

    public function serviceRequest(?string $id, ?string $subject): ServiceRequest
    {
        return $this->one(ServiceRequest::class, $id, $subject, 'subject', 'service request');
    }

    public function workOrder(?string $id, ?string $title): WorkOrder
    {
        return $this->one(WorkOrder::class, $id, $title, 'title', 'work order');
    }

    public function technicianByName(string $name): Technician
    {
        $matches = Technician::query()->whereHas('user', fn ($query) => $query->where('name', $name))->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'technician' => __('Choose one technician in this organization.'),
            ]);
        }

        return $matches->first();
    }

    public function memberByName(string $name): User
    {
        $organizationId = OrganizationContext::id();
        $matches = User::query()
            ->where('name', $name)
            ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationId))
            ->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'assignee' => __('Choose one team member in this organization.'),
            ]);
        }

        return $matches->first();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function one(string $model, ?string $id, ?string $label, string $column, string $noun): Model
    {
        if (is_string($id) && $id !== '') {
            $record = $model::query()->whereKey($id)->first();

            if ($record === null) {
                throw ValidationException::withMessages([
                    $column => __('That :noun was not found in this organization.', ['noun' => $noun]),
                ]);
            }

            return $record;
        }

        if (! is_string($label) || trim($label) === '') {
            throw ValidationException::withMessages([
                $column => __('A :noun is required.', ['noun' => $noun]),
            ]);
        }

        $matches = $model::query()->where($column, trim($label))->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                $column => __('Choose one :noun in this organization.', ['noun' => $noun]),
            ]);
        }

        return $matches->first();
    }
}
