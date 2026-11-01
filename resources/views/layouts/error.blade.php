{{-- エラー画面の枠（behavior.md 3-3 / 決定8）
     共通の枠（上部バー）は通さずカード1枚。見た目は design-guide.md の空状態の型。
     419・500 は未ログインでも出る。500 はデータベースが原因のことが多いので、
     ログイン済みかどうかの判定は例外に耐える形にする（ここで引いて落ちたら元も子もない）。 --}}
@php
    try {
        $loggedIn = auth()->check();
    } catch (\Throwable $e) {
        $loggedIn = false;
    }
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1280">
    <title>@yield('title')｜チームチャットアプリ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth">
    <div class="auth__card">
        <div class="auth__brand"><span class="brandmark">チ</span>チームチャットアプリ</div>
        <div class="empty">
            <p class="empty__title">@yield('heading')</p>
            <p class="empty__text">
                @if ($loggedIn)
                    <a href="{{ url('/channels') }}">チャンネル一覧へ戻る</a>
                @else
                    <a href="{{ url('/login') }}">ログイン</a>
                @endif
            </p>
        </div>
    </div>
</div>
</body>
</html>
