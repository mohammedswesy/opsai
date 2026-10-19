<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCatalogItemRequest;
use App\Http\Requests\UpdateCatalogItemRequest;
use App\Models\Service;
use App\Services\Operations\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request, CatalogService $catalog): Response
    {
        $this->authorize('viewAny', Service::class);

        return Inertia::render('Services/Index', [
            'services' => $catalog->paginate($request),
            'filters' => $request->only(['search', 'status', 'category']),
            'canManage' => $request->user()?->can('create', Service::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Service::class);

        return Inertia::render('Services/Form', ['service' => null]);
    }

    public function store(StoreCatalogItemRequest $request, CatalogService $catalog): RedirectResponse
    {
        $catalog->create($request->validated());

        return redirect()->route('services.index')->with('success', __('Service saved.'));
    }

    public function edit(Service $service): Response
    {
        $this->authorize('update', $service);

        return Inertia::render('Services/Form', ['service' => $service]);
    }

    public function update(UpdateCatalogItemRequest $request, Service $service, CatalogService $catalog): RedirectResponse
    {
        $catalog->update($service, $request->validated());

        return redirect()->route('services.index')->with('success', __('Service saved.'));
    }

    public function destroy(Service $service, CatalogService $catalog): RedirectResponse
    {
        $this->authorize('delete', $service);
        $catalog->archive($service);

        return redirect()->route('services.index')->with('success', __('Service archived.'));
    }
}
