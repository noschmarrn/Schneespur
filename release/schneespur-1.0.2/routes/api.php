<?php

use App\Http\Controllers\Api\OwnTracksController;
use App\Http\Middleware\AuthenticateOwntracks;
use Illuminate\Support\Facades\Route;

Route::post('/owntracks', [OwnTracksController::class, 'store'])
    ->middleware(AuthenticateOwntracks::class);
