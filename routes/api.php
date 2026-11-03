<?php

use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChannelMessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| URLとルート名は permissions-api.md 2-3 の表が正です。
|
| /api の前置きと api ミドルウェアグループ（throttle:api で 60回／分）は
| RouteServiceProvider が付けるので、ここには書きません。
| **ログインも鍵も要りません**（仕様書 3-7）。初期状態の GET /user は
| 削除しました（personal_access_tokens を使わないため・data.md 2-6）。
|
| **読み取り専用です。**GET しか定義しないので、同じURLへの POST・PATCH・DELETE は
| Laravel が MethodNotAllowedHttpException にし、Handler が 405 の文言
| 「このAPIは読み取り専用です」に整形します（behavior.md 3-4）。
|
*/

Route::get('/channels', [ChannelController::class, 'index'])->name('api.channels.index');

// {channel} は数字だけに絞る。数字でないURLはルートの時点で 404 になり、
// 「指定されたURLは見つかりません」になる（routes/web.php と同じ形）。
// プライベートチャンネルの ID と存在しない ID は、どちらもコントローラーの
// public スコープで落ちて「指定されたチャンネルは見つかりません」になる（AC-7-3）。
Route::get('/channels/{channel}/messages', [ChannelMessageController::class, 'index'])
    ->whereNumber('channel')
    ->name('api.channels.messages.index');
