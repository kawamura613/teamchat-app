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
        // この枠は auth の内側でしか描かないので、利用者がいる前提で書く
        // （枠の上部バーも auth()->user()->name を直に読んでいる）。
        View::composer('layouts.app', function (ViewContract $view) {
            $view->with([
                // 画面側が同じ行をすでに引いているときは、それをそのまま使う
                // （SC-03 は一覧とサイドバーが同じ行なので二重に引かない）。
                // 並びは作成された順（questions.md Q-06 の想定）
                'sidebarChannels' => $view->getData()['sidebarChannels']
                    ?? Channel::visibleTo(Auth::user())->orderBy('id')->get(),
                // 選択中は URL の {channel} で見る。SC-05・SC-06・SC-10 で付く
                'selectedChannelId' => request()->route('channel'),
            ]);
        });
    }
}
