<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function errorResponse(string $message = "Bad request", int $statusCode = 400, string $more = "")
    {
        return response()->json([
            'error' => true,
            'message' => $message,
            'metadata' => $more,
        ], $statusCode);
    }
}