<?php

use Illuminate\Support\Facades\Route;
use Modules\Properties\Http\Controllers\Api\V1\PropertiesController;


Route::group(['prefix' => 'v1'], function () {
    Route::get('/properties/xml', [PropertiesController::class, 'generateXml']);
});