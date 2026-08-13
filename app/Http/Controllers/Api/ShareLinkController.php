<?php

namespace App\Http\Controllers\Api;

use App\Events\ShareLinkAccessed;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShareLinkRequest;
use App\Models\File;
use App\Models\ShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShareLinkController extends Controller
{
    /**
     * Cria um link de compartilhamento pro arquivo — só o dono pode gerar.
     */
    public function store(StoreShareLinkRequest $request, File $file): JsonResponse
    {
        $this->authorize('view', $file);

        $validated = $request->validated();

        $shareLink = $file->shareLinks()->create([
            'token' => Str::random(40),
            'expires_at' => now()->addMinutes($validated['expires_in_minutes']),
            'max_uses' => $validated['max_uses'] ?? null,
            'access_count' => 0,
        ]);

        return response()->json([
            'token' => $shareLink->token,
            'url' => route('share-links.show', $shareLink->token),
            'expires_at' => $shareLink->expires_at,
            'max_uses' => $shareLink->max_uses,
            'access_count' => $shareLink->access_count,
        ], 201);
    }

    /**
     * Endpoint público (sem autenticação) que resolve o token e redireciona
     * pra uma presigned URL de download no S3/MinIO.
     *
     * Distinção deliberada entre os status de erro:
     * - 404: o token nunca existiu (não dá pra diferenciar "nunca existiu"
     *   de "id errado" pra quem tenta adivinhar tokens)
     * - 410: o token existiu e foi emitido de verdade, mas não serve mais
     *   (expirou ou atingiu o limite de usos) — o recurso "foi embora",
     *   diferente de "nunca esteve lá"
     */
    public function show(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $shareLink = ShareLink::where('token', $token)->first();

        if (! $shareLink) {
            return response()->json(['message' => 'Link não encontrado.'], 404);
        }

        if (! $shareLink->isValid()) {
            return response()->json(['message' => 'Link expirado ou com limite de usos esgotado.'], 410);
        }

        // Incremento condicional e atômico: só conta o acesso se o link
        // ainda estiver dentro do limite *no momento exato do update*,
        // evitando que duas requisições simultâneas (corrida) ultrapassem
        // max_uses fazendo cada uma sua própria checagem isValid() e
        // incrementando por fora.
        $incremented = ShareLink::where('id', $shareLink->id)
            ->where(function ($query) {
                $query->whereNull('max_uses')
                    ->orWhereColumn('access_count', '<', 'max_uses');
            })
            ->increment('access_count');

        if ($incremented === 0) {
            return response()->json(['message' => 'Link expirado ou com limite de usos esgotado.'], 410);
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $shareLink->file->storage_path,
            now()->addMinutes(5)
        );

        event(new ShareLinkAccessed($shareLink, $request->ip(), $request->userAgent()));

        return redirect()->away($url);
    }
}
