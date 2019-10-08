<?php
namespace App\Http\Classes;

use Illuminate\Support\Facades\Response;
trait ResponseTrait
{
    protected function sendSuccess($data, $message = '', $code = 200)
    {
        return $this->send(true, $data, $message, $code, true);
    }
    protected function sendError($message, $code, $data = [])
    {
        return $this->send(false, $data, $message, $code);
    }
    private function send($status, $data = [], $message = '', $code = '', $isSuccess = false)
    {
        $response = ['status' => $status];
        if (!empty($message)) {
            $response['message'] = $message;
        }
        if (!empty($code)) {
            $response['code'] = $code;
        }
        if (isset($data)) {
            $response['data'] = $data;
        }
        return Response::json($response, $code);
    }
    
}
