<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        // Fortify の既定はメールアドレス欄に auth.failed を出すため、ここで投げ替える。
        // screens.md 4-2「パスワード欄の下に1文だけ・どちらが違うかは明かさない」。
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input(Fortify::username()))->first();

            if ($user && Hash::check($request->input('password'), $user->password)) {
                return $user;
            }

            throw ValidationException::withMessages([
                'password' => 'メールアドレスまたはパスワードが正しくありません',
            ]);
        });

        // behavior.md 3-2 の 429（ログインの試行が多すぎる）の出どころ。
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
