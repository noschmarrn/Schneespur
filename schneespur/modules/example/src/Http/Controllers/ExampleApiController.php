<?php

namespace Schneespur\Module\Example\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ExampleApiController
{
    public function status(): JsonResponse
    {
        $moduleJsonPath = dirname(__DIR__, 3) . '/module.json';
        $moduleData = json_decode(file_get_contents($moduleJsonPath), true);

        return response()->json([
            'status' => 'ok',
            'module' => 'example',
            'version' => $moduleData['version'] ?? 'unknown',
        ]);
    }
}
