<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Público de propósito: quem recebe um link de compartilhamento não tem
// (nem precisa ter) conta no sistema.
Route::get('/share-links/{token}', [ShareLinkController::class, 'show'])->name('share-links.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files', [FileController::class, 'index']);
    Route::get('/files/{file}', [FileController::class, 'show']);
    Route::delete('/files/{file}', [FileController::class, 'destroy']);

    Route::post('/files/{file}/share-links', [ShareLinkController::class, 'store']);
});
