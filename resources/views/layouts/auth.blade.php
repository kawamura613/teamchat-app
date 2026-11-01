{{-- SC-01・SC-02 の枠（screens.md 3-2）。カード1枚・上部バーとサイドバーは無い --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1280">
    <title>@yield('title')｜チームチャットアプリ</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<div class="auth">
    <div class="auth__card">
        <div class="auth__brand"><span class="brandmark">チ</span>チームチャットアプリ</div>
        <h1 class="h-display" style="text-align:center;margin-bottom:var(--sp-6);font-size:var(--fs-title)">@yield('title')</h1>
        @yield('form')
        <div class="auth__foot">@yield('foot')</div>
    </div>
</div>
</body>
</html>
