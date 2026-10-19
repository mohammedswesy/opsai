<?php

namespace App\Http\Controllers;

use App\AI\Tools\AIToolRegistry;
use App\Http\Controllers\Concerns\SharesLookups;
use App\Models\AiConversation;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiPageController extends Controller
{
    use SharesLookups;

    public function __invoke(Request $request, AIToolRegistry $tools, ?AiConversation $conversation = null): Response
    {
        if ($conversation !== null) {
            $this->authorize('view', $conversation);
        }

        $this->rememberBranch($request);
        $pending = $conversation?->actions()->where('status', 'pending')->latest()->first();

        return Inertia::render('Ai/Index', [
            'conversations' => AiConversation::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(30)
                ->get(['id', 'title', 'updated_at']),
            'active' => $conversation?->only(['id', 'title']),
            'messages' => $conversation === null ? [] : $conversation->messages()
                ->whereIn('role', ['user', 'assistant', 'tool'])
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'metadata']),
            'pendingAction' => $pending === null ? null : [
                'id' => $pending->id,
                'tool_name' => $pending->tool_name,
                'status' => $pending->status->value,
                'preview' => $tools->get($pending->tool_name)?->preview($pending->arguments ?? []) ?? [],
            ],
            'branches' => $this->branchOptions(),
            'branchId' => $request->session()->get('current_branch_id'),
        ]);
    }

    private function rememberBranch(Request $request): void
    {
        if (! $request->exists('branch_id')) {
            return;
        }

        $branchId = $request->string('branch_id')->toString();

        if ($branchId === '') {
            $request->session()->forget('current_branch_id');

            return;
        }

        if (Branch::query()->whereKey($branchId)->exists()) {
            $request->session()->put('current_branch_id', $branchId);
        }
    }
}
