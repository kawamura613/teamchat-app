<?php

namespace App\Providers;

use App\Actions\Fortify\AttemptToAuthenticate;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate as FortifyAttemptToAuthenticate;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ログイン失敗の文言をパスワード欄に出すため、照合の一歩だけ差し替える
        // （screens.md 4-2）。Fortify のパイプラインはクラス名をコンテナで解決する。
        $this->app->bind(FortifyAttemptToAuthenticate::class, AttemptToAuthenticate::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        // 画面は自前の Blade（permissions-api.md 2-2・4-3 の #13）。
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));

        // Fortify はパスワード確認の画面を feature と無関係に登録する。
        // この画面は作らないので 404 にする（features.md 2-3 / permissions-api.md 1-5）。
        Fortify::confirmPasswordView(fn () => abort(404));

        // behavior.md 3-2 の 429（ログインの試行が多すぎる）の出どころ。
        RateLimiter::for('login', function (Request $request) {
            // 空で送られると null が来るので文字列に寄せる（PHP の非推奨を踏まない）。
            $username = (string) $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
