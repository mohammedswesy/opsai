<?php

namespace App\Http\Controllers;

use App\AI\Services\AIActionService;
use App\AI\Tools\AIToolRegistry;
use App\Models\AiAction;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiActionController extends Controller
{
    public function confirm(Request $request, AiAction $action, AIActionService $actions, AIToolRegistry $tools): JsonResponse
    {
        $this->authorize('confirm', $action);
        $updated = $actions->confirm($action, $request->user(), $this->branchId($request));
        $tool = $tools->get($updated->tool_name);

        return response()->json([
            'action' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
                'preview' => $tool?->preview($updated->arguments ?? []) ?? [],
            ],
            'result' => $updated->result,
        ]);
    }

    public function reject(Request $request, AiAction $action, AIActionService $actions): JsonResponse
    {
        $this->authorize('confirm', $action);
        $updated = $actions->reject($action, $request->user());

        return response()->json([
            'action' => [
                'id' => $updated->id,
                'status' => $updated->status->value,
            ],
        ]);
    }

    private function branchId(Request $request): ?string
    {
        $sessionBranch = $request->session()->get('current_branch_id');

        if (! is_string($sessionBranch) || $sessionBranch === '') {
            return null;
        }

        return Branch::query()->whereKey($sessionBranch)->exists() ? $sessionBranch : null;
    }
}
