<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFileRequest;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Lista paginada dos arquivos do usuário autenticado — nunca de
     * outro usuário (escopado direto na relação, sem depender só da
     * checagem de autorização por registro).
     */
    public function index(Request $request): JsonResponse
    {
        $files = $request->user()->files()->latest()->paginate(15);

        return response()->json($files);
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        $uploaded = $request->file('file');
        $user = $request->user();

        $filename = Str::uuid().'.'.$uploaded->getClientOriginalExtension();

        $storagePath = Storage::disk('s3')->putFileAs(
            'files/'.$user->id,
            $uploaded,
            $filename
        );

        if ($storagePath === false) {
            return response()->json([
                'message' => 'Falha ao armazenar o arquivo.',
            ], 500);
        }

        $file = $user->files()->create([
            'original_name' => $uploaded->getClientOriginalName(),
            // MIME detectado pelo conteúdo real do arquivo (fileinfo), não
            // pelo header Content-Type informado pelo cliente.
            'mime_type' => $uploaded->getMimeType(),
            'size' => $uploaded->getSize(),
            'storage_path' => $storagePath,
        ]);

        return response()->json($file, 201);
    }

    public function show(Request $request, File $file): JsonResponse
    {
        $this->authorize('view', $file);

        return response()->json($file);
    }

    /**
     * Remove do storage e do banco. Só apaga o registro se a remoção no
     * storage confirmar sucesso (ou o objeto já não existir lá) — evita
     * apagar o registro do banco e deixar o arquivo órfão no MinIO.
     */
    public function destroy(Request $request, File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $disk = Storage::disk('s3');

        if ($disk->exists($file->storage_path) && ! $disk->delete($file->storage_path)) {
            return response()->json([
                'message' => 'Falha ao remover o arquivo do storage.',
            ], 500);
        }

        $file->delete();

        return response()->json(null, 204);
    }
}
