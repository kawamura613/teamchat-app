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

// ログインが要る画面には no-store を付ける。付けないとログアウトしたあとに
// ブラウザの戻るボタンで中身が履歴から出てしまう（acceptance.md AC-1-4）。
// Laravel の既定は no-cache, private で、再検証なしの表示を止められない。
Route::middleware(['auth', 'cache.headers:no_store;no_cache;must_revalidate;max_age=0'])->group(function () {
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
});
