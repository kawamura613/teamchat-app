<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelMemberController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReplyController;
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
    ->whereNumber(['channel', 'message', 'user'])
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

        // SC-09 はプライベートチャンネル専用。公開チャンネルの ID で来たら
        // 404 にする（permissions-api.md 1-4 の △1）。判定は ChannelPolicy。
        // {user} はルートモデルバインディングで引く（論理削除は無い）。
        Route::get('/channels/{channel}/members', [ChannelMemberController::class, 'index'])->name('channels.members.index');
        Route::post('/channels/{channel}/members', [ChannelMemberController::class, 'store'])->name('channels.members.store');
        Route::delete('/channels/{channel}/members/{user}', [ChannelMemberController::class, 'destroy'])->name('channels.members.destroy');

        // {message} はルートモデルバインディングで引く。edit・update・destroy の
        // 3 本は既定のまま（削除済みを除外）にして、削除済みの編集・再削除を
        // ここで 404 にする（permissions-api.md 2-2 の表 / Q-19）。
        //
        // show と replies.store の 2 本だけ ->withTrashed() を付ける。削除された
        // メッセージのスレッドを開けて（questions.md 決定1 の出口1-b）、その跡地に
        // 返信も続けられる（回答6 / 出口1-d）ようにするため。
        //
        // {message} は返信でも同じルートで受ける（data.md 1-3 の決定1。返信も
        // 同じ messages テーブルの行）。
        Route::post('/channels/{channel}/messages', [MessageController::class, 'store'])->name('channels.messages.store');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show')->withTrashed();
        Route::get('/messages/{message}/edit', [MessageController::class, 'edit'])->name('messages.edit');
        Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
        Route::post('/messages/{message}/replies', [ReplyController::class, 'store'])->name('messages.replies.store')->withTrashed();
    });
