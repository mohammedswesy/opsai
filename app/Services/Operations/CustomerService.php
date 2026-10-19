<?php

namespace App\Services\Operations;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CustomerService
{
    public function __construct(private readonly ActivityLogger $activities) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();
        $sort = in_array($request->string('sort')->toString(), ['name', 'city', 'created_at'], true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        return Customer::query()
            ->with('branch:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->string('branch_id')))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        $customer = Customer::query()->create($data);
        $this->activities->log($customer, 'customer.created', $customer->name);

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        $this->activities->log($customer, 'customer.updated', $customer->name);

        return $customer;
    }

    public function archive(Customer $customer): void
    {
        $this->activities->log($customer, 'customer.archived', $customer->name);
        $customer->delete();
    }
}
