<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\AIException;
use App\AI\Services\AIConversationService;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function __invoke(Request $request, AIConversationService $conversations): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.(int) config('ai.max_input_length')],
            'conversation_id' => ['nullable', 'string'],
        ]);

        try {
            return response()->json($conversations->chat(
                $request->user(),
                $data,
                $this->branchId($request),
            ));
        } catch (AIException $exception) {
            Log::warning('ai.chat_failed', [
                'reason' => $exception->context['reason'] ?? 'provider_error',
            ]);

            return response()->json(['message' => $exception->publicMessage], $exception->status);
        }
    }

    private function branchId(Request $request): ?string
    {
        if ($request->exists('branch_id')) {
            $branchId = $request->string('branch_id')->toString();

            if ($branchId === '') {
                $request->session()->forget('current_branch_id');

                return null;
            }

            if (Branch::query()->whereKey($branchId)->doesntExist()) {
                abort(404);
            }

            $request->session()->put('current_branch_id', $branchId);

            return $branchId;
        }

        $sessionBranch = $request->session()->get('current_branch_id');

        if (! is_string($sessionBranch) || $sessionBranch === '') {
            return null;
        }

        return Branch::query()->whereKey($sessionBranch)->exists() ? $sessionBranch : null;
    }
}
