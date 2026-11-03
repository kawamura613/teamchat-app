<?php

namespace App\Services;

use Illuminate\Support\HtmlString;

/**
 * 検索結果の抜粋（F-20 / screens.md 3-5 の SC-08）。
 *
 * **窓の取り方とエスケープの順番を、ここ 1 か所に閉じる。**ビューに置くと
 * 画面ごとに写ることになり、片方だけ直したときに静かに食い違う。
 *
 * 決めは screens.md 3-5 と acceptance.md T-6-11。
 * - **最初に当たった位置を中心**に 100 文字ほどの窓を取る。本文の後ろのほうで
 *   当たっても、強調が窓から外れない
 * - **窓の中の一致はすべて** `<mark>` にする
 * - **切り落とした側にだけ**「…」を付ける
 * - **生の本文で位置を数え、区間ごとに `e()` してから `<mark>` を挟む。**先に
 *   エスケープすると、`&lt;` のような実体参照の中にキーワードが当たって、それを
 *   割ってしまう
 * - 返すのは `HtmlString`。`e()` は `Htmlable` を素通しするので、ビューは `{{ }}`
 *   のままで `<mark>` が出る（`{!! !!}` を書かない）
 *
 * **削除済みの本文は渡ってこない。**検索の問い合わせがそもそも取ってこないので
 * （questions.md 決定1 の出口2 / behavior.md 3-6）、ここに跡地の分岐は無い。
 */
class SearchExcerpt
{
    /** 窓の幅（文字数・screens.md 3-5 の「前後含めて100文字ほど」） */
    private const 幅 = 100;

    public static function make(string $body, string $keyword): HtmlString
    {
        // 必須チェックを通っているので空は来ない。mb_stripos が 0 を返し続けて
        // 無限ループになるのを防ぐための歯止め
        if ($keyword === '') {
            return new HtmlString(e(mb_substr($body, 0, self::幅)));
        }

        $始め = self::窓の始まり($body, $keyword);

        return new HtmlString(
            ($始め > 0 ? '…' : '')
            .self::強調する(mb_substr($body, $始め, self::幅), $keyword)
            .($始め + self::幅 < mb_strlen($body) ? '…' : '')
        );
    }

    /**
     * 最初に当たった位置を中心に窓を置く。
     *
     * 当たらない本文は渡ってこない前提だが、そのときは先頭から取る（抜粋が
     * 空になるより、頭から出ているほうが読める）。
     */
    private static function 窓の始まり(string $body, string $keyword): int
    {
        $位置 = mb_stripos($body, $keyword);

        if ($位置 === false) {
            return 0;
        }

        // キーワードが窓より長いときは、当たった位置から取る
        $前後に割ける分 = max(0, self::幅 - mb_strlen($keyword));

        return max(0, $位置 - intdiv($前後に割ける分, 2));
    }

    /**
     * 窓の中の一致をすべて `<mark>` で包む。
     *
     * 探すときだけ大文字小文字を区別せず（決定10・仮）、**出すのは本文の表記の
     * まま**にする（`Release` を `release` で探しても `Release` と出す）。
     */
    private static function 強調する(string $窓, string $keyword): string
    {
        $語長 = mb_strlen($keyword);
        $html = '';
        $位置 = 0;

        while (($見つけた = mb_stripos($窓, $keyword, $位置)) !== false) {
            $html .= e(mb_substr($窓, $位置, $見つけた - $位置))
                .'<mark>'.e(mb_substr($窓, $見つけた, $語長)).'</mark>';
            $位置 = $見つけた + $語長;
        }

        return $html.e(mb_substr($窓, $位置));
    }
}
