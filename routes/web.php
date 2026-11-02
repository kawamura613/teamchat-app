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
// {channel} は id で引く（data.md 2-2）。数字だけに絞って、数字でない URL は
// ルートの時点で 404 にする（behavior.md 3-2「そのURLがない」）。グループに
// 付けておけば、単位3 で members 系を足したときも自動で効く。
Route::middleware(['auth', 'cache.headers:no_store;no_cache;must_revalidate;max_age=0'])
    ->whereNumber('channel')
    ->group(function () {
        Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');

        // /channels/create は /channels/{channel} より先に書く。あとに書くと
        // {channel} が "create" を飲み込む（permissions-api.md 2-2）。
        Route::get('/channels/create', [ChannelController::class, 'create'])->name('channels.create');
        Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');

        Route::get('/channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
        Route::get('/channels/{channel}/edit', [ChannelController::class, 'edit'])->name('channels.edit');
        Route::patch('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
        Route::get('/channels/{channel}/delete', [ChannelController::class, 'confirmDelete'])->name('channels.delete');
        Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
    });
