<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Serve a file from the 'public' storage disk directly through Laravel —
     * this does not depend on the storage:link symlink or the server's
     * document root being configured correctly, unlike the standard
     * Storage::url() approach that kept failing on this host.
     */
    public function show(string $path)
    {
        abort_if(str_contains($path, '..'), 404); // block path traversal outright
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path));
    }
}