<?php

namespace App;

trait ApiResponses
{
    protected function success($data, string $message = 'Operation Successfully', int $status = 200){
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $status);
    }

    protected function error(string $message, int $status = 400, array $error = []) {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error' => $error
        ], $status);
    }
}
