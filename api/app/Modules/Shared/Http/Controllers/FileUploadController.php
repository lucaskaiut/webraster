<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileUploadController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $path = $request->file('file')->store('uploads', 'public');

        return $this->created([
            'url' => asset("storage/{$path}"),
            'path' => $path,
        ]);
    }
}
