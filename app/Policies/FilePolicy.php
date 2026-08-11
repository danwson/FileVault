<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    /**
     * Só o dono do arquivo pode ver o detalhe.
     */
    public function view(User $user, File $file): bool
    {
        return $user->id === $file->user_id;
    }

    /**
     * Só o dono do arquivo pode apagá-lo.
     */
    public function delete(User $user, File $file): bool
    {
        return $user->id === $file->user_id;
    }
}
