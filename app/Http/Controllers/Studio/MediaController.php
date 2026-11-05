<?php

namespace App\Http\Controllers\Studio;

use App\AI\Exceptions\AiPolicyViolation;
use App\Http\Controllers\Controller;
use App\Models\MediaJob;
use App\Models\Workspace;
use App\Services\Media\MediaService;
use App\Services\Studio\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends Controller
{
    public function store(Request $request, MediaService $media): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:image,speech,video,model3d'],
            'prompt' => ['required', 'string', 'max:4000'],
            'workspace_id' => ['nullable', 'integer'],
            'options' => ['nullable', 'array'],
        ]);
        $workspace = isset($data['workspace_id']) ? Workspace::query()->findOrFail($data['workspace_id']) : null;

        try {
            $job = $media->request($request->user(), $data['type'], $data['prompt'], $workspace, $data['options'] ?? []);
        } catch (AiPolicyViolation $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $job->public_id, 'status' => $job->status, 'credits' => $job->credits], 202);
    }

    public function show(Request $request, MediaJob $media, WorkspaceService $workspaces): JsonResponse
    {
        $this->authorizeMedia($request, $media, $workspaces);

        return response()->json(['id' => $media->public_id, 'type' => $media->type, 'status' => $media->status, 'mime' => $media->mime, 'error' => $media->error]);
    }

    public function file(Request $request, MediaJob $media, WorkspaceService $workspaces): StreamedResponse
    {
        $this->authorizeMedia($request, $media, $workspaces);
        abort_unless($media->status === 'succeeded', 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function authorizeMedia(Request $request, MediaJob $media, WorkspaceService $workspaces): void
    {
        $user = $request->user();
        if ($media->user_id === $user->id) {
            return;
        }
        $workspace = $media->workspace_id ? Workspace::query()->find($media->workspace_id) : null;
        abort_unless($workspace && $workspaces->access($user, $workspace) !== null, 404);
    }
}
