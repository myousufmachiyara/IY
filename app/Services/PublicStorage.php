<?php

namespace App\Services;

class PublicStorage
{
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return route('files.show', ['path' => ltrim($path, '/')]);
    }
}