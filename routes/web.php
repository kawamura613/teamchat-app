<?php

use App\Http\Controllers\ChannelController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| URLとルート名は permissions-api.md 2-2 の表が正です。
| 認証の5本（login・login.store・logout・register・register.store）は
| Fortify が登録するので、ここには書きません。
|
*/

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('channels.index')
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
});
