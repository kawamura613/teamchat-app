<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * 前後の空白を取り除く（screens.md 4-1「全体の決まり」）。
     *
     * Laravel 既定の \s は PCRE_UCP を付けないため ASCII の空白しか当たらず、
     * 日本語入力でよく入る全角スペース（U+3000）が残ってしまう。
     * 「空白だけの送信が通らない」を満たすため、ここで全角スペースも取り除く。
     * $except（パスワード）はそのまま。取り除くのは前後だけで、途中は残す。
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        if (in_array($key, $this->except, true) || ! is_string($value)) {
            return $value;
        }

        return preg_replace('/^[\s\x{3000}\x{FEFF}]+|[\s\x{3000}\x{FEFF}]+$/u', '', $value);
    }
}
