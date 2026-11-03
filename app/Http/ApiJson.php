<?php

namespace App\Http;

/**
 * 公開API の JSON の符号化オプション（permissions-api.md 3-1 / behavior.md 3-4）。
 *
 * Laravel の既定（`JsonResponse::DEFAULT_ENCODING_OPTIONS`）だと「テ」が
 * `"テ"` の形になる。JSON としては同じ値だが、社内ポータル側で目視確認
 * するときに読めないので、エスケープしない側に寄せた。
 *
 * **置き場をここ（`App\Http`）にしている理由**: 本体の応答（リソースの
 * コレクション）とエラーの応答（`App\Exceptions\Handler`）の両方が同じ
 * オプションを通す。例外層からコントローラーの定数を取りに行かせると、
 * コントローラーを動かすだけでエラーの整形が壊れるので、**どちらでもない層**に
 * 1 か所だけ置く。
 */
final class ApiJson
{
    public const OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
}
