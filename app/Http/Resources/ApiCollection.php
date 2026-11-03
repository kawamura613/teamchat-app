<?php

namespace App\Http\Resources;

use App\Http\ApiJson;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 公開API の一覧の包み（permissions-api.md 3-1 / behavior.md 3-4）。
 *
 * `links`・`meta` は Laravel が付ける形をそのまま使う（3-1。独自の形にする
 * 理由が無く、ページ送りが `paginate()` の 1 行で済む）。足しているのは
 * **符号化オプションだけ**。
 *
 * `jsonOptions()` は Laravel が応答を組み立てるときに読む穴
 * （`ResourceResponse::toResponse` が `response()->json(..., $this->resource->jsonOptions())`
 * に渡す）。ここで渡すので**符号化は 1 回だけ**走る。
 * `->response()->setEncodingOptions()` だと、組み立て済みの JSON を復号して
 * 組み直すので、同じペイロードを 3 回なめることになる。
 */
class ApiCollection extends AnonymousResourceCollection
{
    public function jsonOptions(): int
    {
        return ApiJson::OPTIONS;
    }
}
