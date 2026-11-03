<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * F-22 メッセージ 1 件ぶん（permissions-api.md 3-3）。
 *
 * **返す列は許可リストで持つ。**モデルをそのまま JSON にしない
 * （permissions-api.md 3-4 / questions.md 決定1 の出口3）。
 * ここが**削除済みの本文が通る 3 つ目の出口**を塞ぐ側の半分で、もう半分は
 * 「そもそも取ってこない」コントローラー側（behavior.md 3-6）。
 *
 * **返さないもの**（3-4 の裏返し）
 * - 投稿者のユーザーID とメールアドレス … 表示名だけ（Q-31 の想定）
 * - `deleted_at` … 削除済みは並ばないので常に null。返す意味が無い
 * - `edited_at` … 「編集済みかどうか」の真偽値で足りる。**いつ直したかは返さない**（Q-17 の想定）
 * - `updated_at`・`channel_id`・`parent_id`・`user_id` … URL で分かる／返信を返さないので要らない
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // 改行はそのまま（Q-20 の想定）
            'body' => $this->body,
            // 投稿者は**表示名だけ**。user はコントローラーが先読みしている
            'user' => ['name' => $this->user->name],
            // 画面に出すのと同じ created_at。**編集しても動かない**（data.md 2-4 / Q-17）
            'posted_at' => $this->created_at->toIso8601String(),
            // 真偽値だけ。edited_at そのものは返さない
            'edited' => $this->edited_at !== null,
            // **削除済みの返信を含まない数**。コントローラーが withCount の既定で
            // 数えている（画面の「返信◯件」とは向きが逆・behavior.md 1-4 / 回答11）
            'reply_count' => (int) $this->replies_count,
        ];
    }
}
