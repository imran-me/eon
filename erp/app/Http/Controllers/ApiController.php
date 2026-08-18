<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Log;

class ApiController extends Controller
{
    public function serverStatus()
    {
        try {
            $status = 'Ok';
            \Log::info($status);
            return response()->json(['status' => $status]);
        } catch (\Exception $err) {
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }

    public function device($data)
    {
        try {
            \Log::info($data);
            return response()->json(['data' => $data]);
        } catch (\Exception $err) {
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }

    public function attendanceLog($data)
    {
        try {
            \Log::info($data);
            return response()->json(['data' => $data]);
        } catch (\Exception $err) {
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }
}
