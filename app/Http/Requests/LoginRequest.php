<?php

namespace App\Http\Requests;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * SC-01 ログインの入力チェック。
 *
 * Fortify の LoginRequest は rules() だけを持ち messages() がないため、
 * 英語の既定文言が出てしまう。文言は screens.md 4-2 が正なので、
 * ここで日本語に固定する（behavior.md 3-8）。
 *
 * rules() は親のまま（メールアドレス・パスワードがそれぞれ「空でない」）。
 * screens.md 4-2 にメールアドレスの形式チェックは無いので足さない。
 *
 * 組み合わせが違うときの「メールアドレスまたはパスワードが正しくありません」は
 * ここではなく FortifyServiceProvider の authenticateUsing で投げる。
 */
class LoginRequest extends FortifyLoginRequest
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ];
    }
}
