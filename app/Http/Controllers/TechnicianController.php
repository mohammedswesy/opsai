<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Http\Requests\StoreTechnicianRequest;
use App\Http\Requests\UpdateTechnicianRequest;
use App\Models\Technician;
use App\Services\Operations\TechnicianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TechnicianController extends Controller
{
    use SharesLookups;

    public function index(Request $request, TechnicianService $technicians): Response
    {
        $this->authorize('viewAny', Technician::class);

        return Inertia::render('Technicians/Index', [
            'technicians' => $technicians->paginate($request),
            'filters' => $request->only(['search', 'status', 'availability_status', 'branch_id']),
            'branches' => $this->branchOptions(),
            'canManage' => $request->user()?->can('create', Technician::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Technician::class);

        return Inertia::render('Technicians/Form', [
            'technician' => null,
            'branches' => $this->branchOptions(),
            'members' => $this->memberOptions(),
            'services' => $this->serviceOptions(),
        ]);
    }

    public function store(StoreTechnicianRequest $request, TechnicianService $technicians): RedirectResponse
    {
        $data = $request->safe()->except('skills');
        $technician = $technicians->create($data, $request->validated('skills') ?? []);

        return redirect()->route('technicians.show', $technician)->with('success', __('Technician saved.'));
    }

    public function show(Technician $technician): Response
    {
        $this->authorize('view', $technician);

        return Inertia::render('Technicians/Show', [
            'technician' => $technician->load(['user:id,name,email', 'branch:id,name', 'skills.service:id,name']),
            'canManage' => request()->user()?->can('update', $technician) ?? false,
        ]);
    }

    public function edit(Technician $technician): Response
    {
        $this->authorize('update', $technician);

        return Inertia::render('Technicians/Form', [
            'technician' => $technician->load('skills'),
            'branches' => $this->branchOptions(),
            'members' => $this->memberOptions(),
            'services' => $this->serviceOptions(),
        ]);
    }

    public function update(UpdateTechnicianRequest $request, Technician $technician, TechnicianService $technicians): RedirectResponse
    {
        $technicians->update($technician, $request->safe()->except('skills'), $request->validated('skills') ?? []);

        return redirect()->route('technicians.show', $technician)->with('success', __('Technician saved.'));
    }

    public function destroy(Technician $technician, TechnicianService $technicians): RedirectResponse
    {
        $this->authorize('delete', $technician);
        $technicians->archive($technician);

        return redirect()->route('technicians.index')->with('success', __('Technician archived.'));
    }
}
