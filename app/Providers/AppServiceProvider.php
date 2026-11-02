<?php

namespace App\Providers;

use App\Http\Requests\LoginRequest;
use App\Models\Channel;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ログインのエラー文を日本語にするため、Fortify の LoginRequest を差し替える
        // （screens.md 4-2 / behavior.md 3-8）。
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // サイドバーに出すチャンネルと、選択中の行（F-04 / screens.md 3-1）。
        // 共通の枠を使う画面すべてで同じものを出すので、コントローラーごとには
        // 渡さない（permissions-api.md 1-6）。絞り込みは visibleTo 1 か所を通す。
        View::composer('layouts.app', function (ViewContract $view) {
            $user = Auth::user();

            $view->with([
                'sidebarChannels' => $user
                    // 作成された順（questions.md Q-06 の想定）
                    ? Channel::visibleTo($user)->orderBy('id')->get()
                    : collect(),
                // 選択中は URL の {channel} で見る。SC-05・SC-06・SC-10 で付く
                'selectedChannelId' => request()->route('channel'),
            ]);
        });
    }
}
