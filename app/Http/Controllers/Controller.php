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

    protected static function GENERATE_TOKEN($length = 15, $data = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
    {
        $str = '';
        $charset = $data;
        $count = strlen($charset);
        while ($length--) {
            $str .= $charset[mt_rand(0, $count - 1)];
        }

        return $str;
    }

    protected static function SUCCESS($message = 'Operation was successful', $data = [], $code = 200)
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => $data], $code);
    }

    protected static function ERROR($message = 'An error occured', $data = [], $code = 400)
    {
        // $data = $data instanceof Object ? $data->getMessage() : $data->getMessage();
        $data = is_array($data) ? $data : $data->getMessage();
        return response()->json(['status' => 'failed', 'message' => $message, 'data' => $data], $code);
    }

    protected static function INPUT_IS_VALID($input)
    {
        if ($input && isset($input) && $input !== null && $input !== '') {
            return true;
        } else {
            return false;
        }
    }
}