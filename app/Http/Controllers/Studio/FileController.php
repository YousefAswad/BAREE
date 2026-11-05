<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeFile;
use App\Models\Workspace;
use App\Services\Files\FileIntakeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class FileController extends Controller
{
    public function store(Request $request, FileIntakeService $intake): JsonResponse
    {
        $this->authorize('workspaces.manage_own');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('baree.uploads.max_mb', 50) * 1024)],
            'workspace_id' => ['nullable', 'integer'],
            'usage' => ['nullable', 'in:always,on_demand,off'],
        ]);

        $owner = $request->user();
        if (isset($data['workspace_id'])) {
            $owner = Workspace::query()->findOrFail($data['workspace_id']);
            $this->authorize('update', $owner);
        }

        try {
            $file = $intake->store($request->file('file'), $request->user(), $owner, $data['usage'] ?? 'on_demand');
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $file->id, 'name' => $file->original_name, 'status' => $file->status], 201);
    }

    public function show(Request $request, KnowledgeFile $file): JsonResponse
    {
        $this->authorizeFile($request, $file);

        return response()->json($file->only(['id', 'original_name', 'mime', 'size', 'status', 'usage_mode', 'text_chars', 'rejection_reason']));
    }

    public function destroy(Request $request, KnowledgeFile $file): JsonResponse
    {
        $this->authorizeFile($request, $file, edit: true);
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return response()->json(null, 204);
    }

    private function authorizeFile(Request $request, KnowledgeFile $file, bool $edit = false): void
    {
        abort_if($file->tenant_id === null, 404); // BAREE agent files are never exposed to institutions
        $user = $request->user();

        if ($file->owner_type === $user->getMorphClass()) {
            abort_unless((int) $file->owner_id === $user->id, 404);

            return;
        }

        $workspace = Workspace::query()->find($file->owner_id) ?? abort(404);
        $this->authorize($edit ? 'update' : 'view', $workspace);
    }
}
