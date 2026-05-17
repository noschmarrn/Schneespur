<?php

namespace Schneespur\Module\Example\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExampleSettingsController extends Controller
{
    public function index(Request $request)
    {
        return view('example-module::settings', [
            'moduleName' => 'Example Module',
            'version' => '1.0.0',
        ]);
    }
}
