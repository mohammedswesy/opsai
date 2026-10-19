<?php

namespace App\Services\Operations;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ServiceRequestService
{
    public function __construct(private readonly ActivityLogger $activities) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();

        return ServiceRequest::query()
            ->with(['customer:id,name', 'service:id,name', 'branch:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->string('branch_id')))
            ->latest('requested_at')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ServiceRequest
    {
        $request = ServiceRequest::query()->create($data);
        $this->activities->log($request, 'request.created', $request->subject);
        $this->activities->log($request->customer, 'request.created', $request->subject, [
            'service_request_id' => $request->id,
        ]);

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ServiceRequest $serviceRequest, array $data): ServiceRequest
    {
        $previous = $serviceRequest->status->value;
        $serviceRequest->update($data);

        if ($previous !== $serviceRequest->status->value) {
            $this->activities->log($serviceRequest, 'request.status_changed', $serviceRequest->status->value, [
                'from' => $previous,
                'to' => $serviceRequest->status->value,
            ]);
        }

        return $serviceRequest;
    }

    public function mark(ServiceRequest $serviceRequest, ServiceRequestStatus $status): void
    {
        if ($serviceRequest->status === $status) {
            return;
        }

        $from = $serviceRequest->status->value;
        $serviceRequest->update(['status' => $status]);
        $this->activities->log($serviceRequest, 'request.status_changed', $status->value, [
            'from' => $from,
            'to' => $status->value,
        ]);
    }
}
