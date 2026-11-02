<?php

namespace App\Http\Controllers\Concerns;

/**
 * メッセージ本文の入力チェック（screens.md 4-6）。
 *
 * **新しく書くときも、その場で書き換えるときも、返信も、同じチェックを同じ数だけ**
 * かける。screens.md 4-6 は SC-06 と SC-07 に同じ8行を並べており、T-5-10 が
 * 「返信もメッセージと同じ数だけかかる」を見る。物差しを2つにすると、片方に
 * ルールを足したときに静かに食い違う。
 *
 * 置き場はコントローラ側のまま（behavior.md 3-8 の表）。使う側は
 * `validate()` / `Validator::make()` の **第2引数・第3引数**にそのまま渡すので、
 * 文言が検査の呼び出しのそばにある形は変わらない。
 *
 * 前後の空白と改行は TrimStrings が落とし（全角スペースも落とすよう上書き済み）、
 * 空になった値は ConvertEmptyStringsToNull が null にするので required で弾ける。
 * 上限もその後の字数で数え、保存もその値（screens.md 4-1 / behavior.md 3-8）。
 */
trait ValidatesMessageBody
{
    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            // bail を先頭に置く。無いと required と max のエラー文が同時に出る
            'body' => ['bail', 'required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'body.required' => 'メッセージを入力してください',
            // 配列などが送られてきたときも欄の文言を出す（screens.md 4-1）
            'body.string' => 'メッセージを入力してください',
            'body.max' => 'メッセージは1,000文字以内で入力してください',
        ];
    }
}
