<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\AttemptToAuthenticate as FortifyAttemptToAuthenticate;

/**
 * ログインの照合（F-02）。
 *
 * Fortify の既定はメールアドレス欄に auth.failed（英語）を出すが、screens.md 4-2 は
 * 「パスワード欄の下に1文だけ・どちらの欄が違うかは明かさない」と決めている。
 * そこで投げる所だけを差し替える。
 *
 * authenticateUsing で自前に照合せず親の guard->attempt に任せるのは、
 * 認証イベントとパスワードの再ハッシュ（EloquentUserProvider）を素通りさせないため。
 */
class AttemptToAuthenticate extends FortifyAttemptToAuthenticate
{
    /**
     * @param  Request  $request
     * @return void
     *
     * @throws ValidationException
     */
    protected function throwFailedAuthenticationException($request)
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            'password' => 'メールアドレスまたはパスワードが正しくありません',
        ]);
    }
}
