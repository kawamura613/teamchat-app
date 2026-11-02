<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * URL の {channel} は、必ず「見られるチャンネル」に絞って取る。
     *
     * 見られないものはここで 404 になる。ポリシーに任せると 403 が返り、
     * 「そこに部屋がある」ことが漏れる（permissions-api.md 1-5・1-6）。
     * 作成者かどうか・本人かどうかは、これを通したあとに authorize で見る。
     *
     * **絞り込みは visibleTo スコープ 1 か所だけを通す**（CLAUDE.md）。
     * チャンネル・メッセージ・メンバーの 3 つの入口が同じ物差しを使うので、
     * ここに置いてコピーを増やさない。
     */
    protected function visibleChannel(Request $request, int $id): Channel
    {
        return Channel::visibleTo($request->user())->findOrFail($id);
    }
}
