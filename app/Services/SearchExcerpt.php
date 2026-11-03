<?php

namespace App\Services;

use Illuminate\Support\HtmlString;

/**
 * 検索結果の抜粋（F-20 / screens.md 3-5 の SC-08）。
 *
 * **窓の取り方とエスケープの順番を、ここ 1 か所に閉じる。**ビューに置くと
 * 画面ごとに写ることになり、片方だけ直したときに静かに食い違う
 * （behavior.md 3-6 の出口2）。
 *
 * 決めは screens.md 3-5 と acceptance.md T-6-11。
 * - **最初に当たった位置を中心**に 100 文字ほどの窓を取る
 * - **窓の中の一致はすべて** `<mark>` にする
 * - **切り落とした側にだけ**「…」を付ける
 * - **本文の末尾に寄ったときは前へ寄せ直す**（窓が 100 文字より短くならない）
 * - **キーワードが窓より長いときは窓を広げる**（窓にキーワード全体が入らないと
 *   強調が 1 つも出ない）
 * - **探すときは DB と同じだけ畳む**（下の `畳む()`）。畳めない揺れで当たった
 *   ときは**先頭から・強調なし**で出す
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
        $本文長 = mb_strlen($body);
        $語長 = mb_strlen($keyword);
        // キーワードが窓より長いときは窓を広げる。広げないと窓にキーワード全体が
        // 入らず、強調が 1 つも出ない（screens.md 3-5）
        $窓幅 = max(self::幅, $語長);

        $位置 = self::当たった位置($body, $keyword);
        $始め = $位置 === null ? 0 : self::窓の始まり($本文長, $位置, $窓幅, $語長);
        $窓 = mb_substr($body, $始め, $窓幅);

        return new HtmlString(
            ($始め > 0 ? '…' : '')
            // 畳めない揺れ（半角カナ・アクセント付きの字）で当たったときは、位置が
            // 取れないので強調なしで出す（screens.md 3-5）
            .($位置 === null ? e($窓) : self::強調する($窓, $keyword))
            .($始め + $窓幅 < $本文長 ? '…' : '')
        );
    }

    /**
     * 畳んでから、最初に当たった位置を取る。
     *
     * 当たらなければ null。DB は当てたのに取れないのは、畳めない揺れ
     * （半角カナ・アクセント）で当たったときだけ（questions.md 決定10）。
     */
    private static function 当たった位置(string $body, string $keyword): ?int
    {
        // required を通った値しか来ないが、空だと mb_stripos が 0 を返し続けて
        // 強調する() の while が進まない。公開の入口なので歯止めを残す
        if ($keyword === '') {
            return null;
        }

        [$探す本文, $探す語] = self::探す対($body, $keyword);
        $位置 = mb_stripos($探す本文, $探す語);

        return $位置 === false ? null : $位置;
    }

    /**
     * 窓の左端を決める。
     *
     * 当たった位置を中心に置き、**本文の末尾に寄ったときは前へ寄せ直す**。
     * 寄せ直さないと、末尾で当たったときに窓が 100 文字より短くなり、前方に
     * 余裕があるのに使われない（screens.md 3-5）。
     */
    private static function 窓の始まり(int $本文長, int $位置, int $窓幅, int $語長): int
    {
        $始め = $位置 - intdiv($窓幅 - $語長, 2);

        return max(0, min($始め, $本文長 - $窓幅));
    }

    /**
     * 窓の中の一致をすべて `<mark>` で包む。
     *
     * 探すのは畳んだ側、**出すのは本文の表記のまま**（`りりーす` で探しても
     * `リリース` と出す）。
     */
    private static function 強調する(string $窓, string $keyword): string
    {
        [$探す窓, $探す語] = self::探す対($窓, $keyword);

        $語長 = mb_strlen($keyword);
        $html = '';
        $位置 = 0;

        while (($見つけた = mb_stripos($探す窓, $探す語, $位置)) !== false) {
            $html .= e(mb_substr($窓, $位置, $見つけた - $位置))
                .'<mark>'.e(mb_substr($窓, $見つけた, $語長)).'</mark>';
            $位置 = $見つけた + $語長;
        }

        return $html.e(mb_substr($窓, $位置));
    }

    /**
     * 探すための対を返す。
     *
     * **畳んで文字数が変わるときは、畳む前の対を返す。**畳んだ側で取った位置を
     * 生の本文に当てるので、文字数が 1 文字でもずれると強調の位置がずれる。
     *
     * @return array{string, string}
     */
    private static function 探す対(string $文字列, string $keyword): array
    {
        $畳んだ文字列 = self::畳む($文字列);
        $畳んだ語 = self::畳む($keyword);

        if (mb_strlen($畳んだ文字列) === mb_strlen($文字列)
            && mb_strlen($畳んだ語) === mb_strlen($keyword)) {
            return [$畳んだ文字列, $畳んだ語];
        }

        return [$文字列, $keyword];
    }

    /**
     * DB の照合順序（`utf8mb4_unicode_ci`）が同じ字として扱うもののうち、
     * **文字数が変わらないぶんだけ**畳む（questions.md 決定10 / data.md 4-4）。
     *
     * - `C` … ひらがな → カタカナ（`りりーす` → `リリース`）
     * - `a` … 全角英数 → 半角（`ＡＰＩ` → `API`）
     *
     * 大文字小文字は `mb_stripos` が見るので、ここでは触らない。
     *
     * **半角カナ（`K`）は畳まない。**`ｶﾞ` → `ガ` で 2 文字が 1 文字になり、位置が
     * ずれる。**アクセント付きの字も同じ**（`café` → `cafe` は分解で増える）。
     * この 2 つで当たったときは、強調なしで先頭から出す。
     */
    private static function 畳む(string $文字列): string
    {
        return mb_convert_kana($文字列, 'aC');
    }
}
