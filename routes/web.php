<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return [
        "data"=> config("app.url"),
        "code"=> 10000,
        "message"=> "SUCCESS"
    ];
});

Route::get('/application/privacy', function () {
    return view('shopify-privacy');
});

Route::get('/print/send/label', [\App\Http\Controllers\Admin\TestController::class, 'printSendLable']);
