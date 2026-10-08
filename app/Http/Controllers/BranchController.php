<?php

namespace App\Http\Controllers;

use App\Enums\BranchStatus;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Branch::class);

        return Inertia::render('Branches/Index', [
            'branches' => Branch::query()->orderBy('name')->get()->map(fn (Branch $branch) => $this->payload($branch)),
            'canManage' => request()->user()?->can('create', Branch::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('Branches/Form', [
            'branch' => null,
            'countries' => config('opsai.countries'),
            'statuses' => BranchStatus::values(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['country'] = strtoupper($data['country']);
        $data['code'] = strtoupper($data['code']);

        Branch::query()->create($data);

        return redirect()->route('branches.index')->with('success', __('Branch created.'));
    }

    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        return Inertia::render('Branches/Form', [
            'branch' => $this->payload($branch),
            'countries' => config('opsai.countries'),
            'statuses' => BranchStatus::values(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();
        $data['country'] = strtoupper($data['country']);
        $data['code'] = strtoupper($data['code']);

        $branch->update($data);

        return redirect()->route('branches.index')->with('success', __('Branch updated.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);
        $branch->delete();

        return redirect()->route('branches.index')->with('success', __('Branch archived.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'address' => $branch->address,
            'city' => $branch->city,
            'country' => $branch->country,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'phone' => $branch->phone,
            'timezone' => $branch->timezone,
            'status' => $branch->status->value,
        ];
    }
}
