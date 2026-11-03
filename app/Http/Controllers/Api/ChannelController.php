<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ApiCollection;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;

/**
 * F-21 公開チャンネルの一覧（permissions-api.md 3-2）。
 *
 * `GET /api/channels`
 *
 * **呼ぶ相手を判定に使わない**（permissions-api.md 1-3）。ログインしていても
 * いなくても、プライベートチャンネルの作成者やメンバーであっても、返るものは
 * 同じ。だから `visibleTo` ではなく `public` スコープを通す（T-7-02）。
 */
class ChannelController extends Controller
{
    public function index(): ApiCollection
    {
        $channels = Channel::public()
            // 作成者の表示名を返すので先読みする（CLAUDE.md の N+1 の決め）
            ->with('creator')
            // 作られた順。サイドバーの並びと揃える（3-2 / Q-06 の想定）
            ->orderBy('id')
            ->paginate(self::件数);

        // ApiCollection が符号化オプションを持つ（behavior.md 3-4）。
        // Responsable なので、返すだけで Laravel が JSON にする
        return new ApiCollection($channels, ChannelResource::class);
    }
}
