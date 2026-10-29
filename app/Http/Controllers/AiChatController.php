<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\AIException;
use App\AI\Services\AIConversationService;
use App\AI\Support\AIErrorMessages;
use App\Models\Branch;
use App\Support\OrganizationContext;
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

        // API routes skip the web SetLocale middleware, so take the interface locale here.
        $locale = self::interfaceLocale($request);
        app()->setLocale($locale);

        try {
            return response()->json($conversations->chat(
                $request->user(),
                $data,
                $this->branchId($request),
                $locale,
            ));
        } catch (AIException $exception) {
            Log::warning('ai.chat_failed', [
                'reason' => $exception->reason(),
            ]);

            return response()->json([
                'message' => $exception->userMessage($locale),
                'reason' => AIErrorMessages::key($exception->reason()),
            ], $exception->status);
        }
    }

    /** The session locale the user picked, else the organization locale, else the app default. */
    public static function interfaceLocale(Request $request): string
    {
        $locales = config('opsai.locales');
        $session = $request->hasSession() ? $request->session()->get('locale') : null;
        $organization = OrganizationContext::current()?->organization->locale;

        foreach ([$session, $organization] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $locales, true)) {
                return $candidate;
            }
        }

        return (string) config('app.locale');
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
