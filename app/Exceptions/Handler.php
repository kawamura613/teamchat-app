<?php

namespace App\Exceptions;

use App\Http\ApiJson;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 公開API（F-21・F-22）のエラーの返し方（behavior.md 3-4 /
        // permissions-api.md 3-5）。
        //
        // **判定は「URL が api・api/* かどうか」で行う。expectsJson() では分けない。**
        // acceptance.md 2-7 は「ブラウザのアドレス欄にURLを入れて、返ってきた
        // JSON を目で見る」と決めているので、公開API にも Accept: text/html で
        // 来る。expectsJson() で分けると、そのとき画面側の HTML が返り、AC-7-3 の
        // 「存在しないIDを入れたときとまったく同じ404・同じ文言」を満たせない。
        //
        // `api/*` だけだと**前置きだけの /api に当たらず**、そこだけ画面側の
        // HTML が返る。api 単独も見る。
        //
        // それ以外は null を返して既定のまま通す。画面側の
        // resources/views/errors/*.blade.php の振る舞いは変えない。
        $this->renderable(function (Throwable $e, Request $request): ?JsonResponse {
            return $request->is('api', 'api/*') ? $this->api用に整形する($e) : null;
        });
    }

    /**
     * 公開API のエラー 1 本（behavior.md 3-4 の表）。
     *
     * 形は `{"message": "…"}` の 1 つだけ（permissions-api.md 3-5）。
     * **例外が持つ応答ヘッダーは落とさない**（405 の `Allow`・429 の
     * `Retry-After` と `X-RateLimit-*`）。Laravel 既定の `prepareJsonResponse` と
     * 同じ扱いにする。
     */
    private function api用に整形する(Throwable $e): JsonResponse
    {
        [$status, $message] = $this->ステータスと文言($e);

        return response()->json(
            ['message' => $message],
            $status,
            $e instanceof HttpExceptionInterface ? $e->getHeaders() : [],
            ApiJson::OPTIONS
        );
    }

    /**
     * @return array{int, string}
     */
    private function ステータスと文言(Throwable $e): array
    {
        return match (true) {
            // ModelNotFoundException は、ここへ来る前に Laravel の prepareException が
            // NotFoundHttpException に包み替えて、元の例外を previous に入れている
            // （vendor の Foundation\Exceptions\Handler::prepareException）。
            // **包みと中身の両方を見る。**「previous が ModelNotFound」だけで判定すると、
            // それを文脈として包み直した将来の 500 が 404 に化ける
            $e instanceof NotFoundHttpException
                && $e->getPrevious() instanceof ModelNotFoundException => [404, '指定されたチャンネルは見つかりません'],
            $e instanceof MethodNotAllowedHttpException => [405, 'このAPIは読み取り専用です'],
            $e instanceof ThrottleRequestsException => [429, 'リクエストが多すぎます。しばらくしてからお試しください'],
            $e instanceof NotFoundHttpException => [404, '指定されたURLは見つかりません'],
            // **表に無い HTTP の例外は、元のステータスを保つ。**500 に畳むと、
            // 保守モード（php artisan down）の 503 が 500 になる
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                'ただいまご利用いただけません。しばらくしてからお試しください',
            ],
            // それ以外は 500。**例外のクラス名・ファイル名・SQL・本文を出さない。**
            // APP_DEBUG の値で出し分けない（true のまま動かしても漏れないように
            // するため）。中身を見るときは storage/logs/laravel.log（behavior.md 3-7）
            default => [500, 'サーバ側で問題が発生しました'],
        };
    }
}
