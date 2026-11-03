<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * F-21 公開チャンネル 1 件ぶん（permissions-api.md 3-2）。
 *
 * **返す列は許可リストで持つ。**モデルをそのまま JSON にしない
 * （permissions-api.md 3-4 / questions.md 決定1 の出口3）。
 * あとから `channels` に列が増えても、ここに書かないかぎり社外には出ない。
 *
 * **返さないもの**（3-4 の裏返し）
 * - `is_private` … 公開チャンネルしか返さないので常に false。情報が無い
 * - 作成者のユーザーID とメールアドレス … 表示名だけで足りる（Q-31 の想定）
 * - `updated_at` … 返す約束が無い
 */
class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // 空のまま作られていれば null（data.md 2-2）
            'description' => $this->description,
            // 作成者は**表示名だけ**（Q-31 の想定）。creator は
            // コントローラーが先読みしている（N+1 を避ける）
            'created_by' => ['name' => $this->creator->name],
            // ISO 8601。config/app.php の Asia/Tokyo に乗って +09:00 が付く
            // （permissions-api.md 3-1・4-1 の #4）
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
