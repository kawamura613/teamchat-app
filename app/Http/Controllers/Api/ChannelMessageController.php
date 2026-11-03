<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ApiCollection;
use App\Http\Resources\MessageResource;
use App\Models\Channel;

/**
 * F-22 公開チャンネルのメッセージ一覧（permissions-api.md 3-3）。
 *
 * `GET /api/channels/{channel}/messages`
 */
class ChannelMessageController extends Controller
{
    /**
     * `{channel}` はモデルで型を書かない。
     *
     * 型を `Channel` にすると Laravel の暗黙のバインディングが効いてしまい、
     * **プライベートチャンネルでも解決される**。そのあと落とすと 403 になり、
     * 「そこに部屋がある」ことが漏れる（permissions-api.md 1-5）。
     * ルートで `whereNumber` を掛けているので、ここには数字しか来ない。
     */
    public function index(int $channel): ApiCollection
    {
        // **公開チャンネルに絞って取る**（permissions-api.md 1-6 の public スコープ）。
        // プライベートの ID も存在しない ID も、同じ ModelNotFoundException に
        // なるので、Handler が**同じ 404・同じ文言**に整形する（3-5 / AC-7-3）。
        // ここで文言を言い分けた瞬間に、仕様書 3-7 が破れる。
        $対象 = Channel::public()->findOrFail($channel);

        $messages = $対象->messages()
            // **withTrashed() を書かない。**削除済みは ID も本文も返さない
            // （questions.md 決定1 の出口3 / behavior.md 3-6）。ここに足すと、
            // 社外から消したはずの本文が読める。いちばん重い漏れ方
            //
            // 返信は一覧に混ぜない。reply_count の数だけで表す（3-3 / Q-31）
            ->whereNull('parent_id')
            // 投稿者の表示名を返すので先読みする（CLAUDE.md の N+1 の決め）
            ->with('user')
            // **画面用の Message::replyCount() は使わない。**あちらは
            // withTrashed() を足して削除済みの返信まで数えるが、API は数えない
            // （behavior.md 1-4 / 回答11）。withCount の既定なら SoftDeletes の
            // グローバルスコープが効いて、削除済みが落ちる
            ->withCount('replies')
            // 新しい順。同じ時刻のときは id で並び順を決める
            // （3-3 / Q-32 の想定。**画面の SC-06 は古い順なので向きが逆**）
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::件数);

        return new ApiCollection($messages, MessageResource::class);
    }
}
