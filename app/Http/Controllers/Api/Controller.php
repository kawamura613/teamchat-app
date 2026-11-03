<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller as BaseController;

/**
 * 公開API の 2 本に共通の決まり（permissions-api.md 3-1）。
 *
 * **日本語をエスケープせずに返す**ための符号化オプションを、ここ 1 か所に持つ。
 * Laravel の既定（`JsonResponse::DEFAULT_ENCODING_OPTIONS`）だと「テ」が
 * `"テ"` の形になる。JSON としては同じ値だが、社内ポータル側で目視確認
 * するときに読めないので、エスケープしない側に寄せた（3-1）。
 *
 * **エラー応答（404・405・429・500）も同じオプションを通す。**
 * `app/Exceptions/Handler.php` がこの定数を参照する（behavior.md 3-4）。
 * 文言が日本語なので、片方だけ素のままにすると応答の形が揺れる。
 *
 * 画面側の基底（`App\Http\Controllers\Controller`）は継承しない。あちらが持つ
 * `visibleChannel()` は利用者を引数に取るが、**公開API は呼ぶ相手を判定に
 * 使わない**（permissions-api.md 1-3）ので、持ち込むと誤用のもとになる。
 */
abstract class Controller extends BaseController
{
    /** JSON の符号化オプション（permissions-api.md 3-1） */
    public const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** 1 回に返す件数。続きは `?page=2`（permissions-api.md 3-1 / Q-32 の想定） */
    protected const 件数 = 50;

    /**
     * リソースコレクションを、日本語をエスケープしない JSON で返す。
     *
     * `links`・`meta` は Laravel が付ける形をそのまま使う（3-1。独自の形に
     * する理由が無く、ページ送りが `paginate()` の 1 行で済む）。
     */
    protected function json(ResourceCollection $resources): JsonResponse
    {
        return $resources->response()->setEncodingOptions(self::JSON_OPTIONS);
    }
}
