<?php

namespace App\Services\Operations;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CatalogService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        return Service::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Service
    {
        return Service::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Service $service, array $data): Service
    {
        $service->update($data);

        return $service;
    }

    public function archive(Service $service): void
    {
        $service->delete();
    }
}
