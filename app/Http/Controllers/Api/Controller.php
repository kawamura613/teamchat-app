<?php

namespace App\Http\Controllers\Api;

use Illuminate\Routing\Controller as BaseController;

/**
 * 公開API の 2 本に共通の決まり（permissions-api.md 3-1）。
 *
 * JSON の符号化オプションは `App\Http\ApiJson` に、応答の包みは
 * `App\Http\Resources\ApiCollection` にある（behavior.md 3-4）。ここには
 * 件数だけを置く。
 *
 * 画面側の基底（`App\Http\Controllers\Controller`）は継承しない。あちらが持つ
 * `visibleChannel()` は利用者を引数に取るが、**公開API は呼ぶ相手を判定に
 * 使わない**（permissions-api.md 1-3）ので、持ち込むと誤用のもとになる。
 */
abstract class Controller extends BaseController
{
    /** 1 回に返す件数。続きは `?page=2`（permissions-api.md 3-1 / Q-32 の想定） */
    protected const 件数 = 50;
}
