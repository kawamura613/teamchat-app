<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ChannelController extends Controller
{
    /**
     * SC-03 チャンネル一覧（F-06）。
     *
     * 単位1 では見出しだけを出します。一覧の行・空状態の枠・「チャンネルを作る」は
     * 単位2 で Channel の visibleTo スコープと一緒に入れます（permissions-api.md 1-6）。
     */
    public function index(): View
    {
        return view('channels.index');
    }
}
