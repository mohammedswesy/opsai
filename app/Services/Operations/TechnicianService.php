<?php

namespace App\Services\Operations;

use App\Models\Technician;
use App\Models\TechnicianSkill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicianService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        return Technician::query()
            ->with(['user:id,name,email', 'branch:id,name', 'skills'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('employee_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('availability_status'), fn ($query) => $query->where('availability_status', $request->string('availability_status')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->string('branch_id')))
            ->orderBy('employee_code')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{skill: string, service_id?: string|null}>  $skills
     */
    public function create(array $data, array $skills): Technician
    {
        return DB::transaction(function () use ($data, $skills) {
            $technician = Technician::query()->create($data);
            $this->syncSkills($technician, $skills);

            return $technician->load('skills', 'user');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{skill: string, service_id?: string|null}>  $skills
     */
    public function update(Technician $technician, array $data, array $skills): Technician
    {
        return DB::transaction(function () use ($technician, $data, $skills) {
            $technician->update($data);
            $this->syncSkills($technician, $skills);

            return $technician->load('skills', 'user');
        });
    }

    public function archive(Technician $technician): void
    {
        $technician->delete();
    }

    /**
     * @param  list<array{skill: string, service_id?: string|null}>  $skills
     */
    private function syncSkills(Technician $technician, array $skills): void
    {
        $technician->skills()->delete();

        foreach ($skills as $skill) {
            TechnicianSkill::query()->create([
                'technician_id' => $technician->id,
                'skill' => $skill['skill'],
                'service_id' => $skill['service_id'] ?? null,
            ]);
        }
    }
}
