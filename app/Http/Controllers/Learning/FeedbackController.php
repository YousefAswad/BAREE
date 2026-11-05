<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Services\Learning\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FeedbackController extends Controller
{
    public function store(Request $request, AiRun $run, FeedbackService $feedback): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'reasons' => ['nullable', 'array', 'max:5'], 'reasons.*' => ['string', 'in:'.implode(',', FeedbackService::REASONS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $feedback->rate($request->user(), $run, $data['rating'], $data['reasons'] ?? [], $data['comment'] ?? null);

        return response()->json(['ok' => true], 201);
    }
}
