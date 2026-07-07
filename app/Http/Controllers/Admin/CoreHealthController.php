<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CoreHealthController extends Controller
{
    public function __invoke(): Response
    {
        return response('OK');
    }
}
