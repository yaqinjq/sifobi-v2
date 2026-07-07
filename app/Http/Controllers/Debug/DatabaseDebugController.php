<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DatabaseDebugController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'db' => DB::connection()->getDatabaseName(),
            'tables' => DB::select('SHOW TABLES'),
        ]);
    }
}
