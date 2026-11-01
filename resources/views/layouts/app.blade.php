{{-- 共通の枠（F-04 / screens.md 3-1）。
     単位1 では上部バーだけ。サイドバーとチャンネル行は単位2、検索窓は単位6 で足す。
     サイドバーが無いあいだは .app__body（260px 固定の2カラム）を使わない。 --}}
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
<div class="app">
    <header class="topbar">
        <a class="topbar__brand" href="{{ route('channels.index') }}">
            <span class="brandmark">チ</span>チームチャットアプリ
        </a>
        <div></div>
        <div class="topbar__me">
            <span class="nm">{{ auth()->user()->name }}</span>
            {{-- リンクを踏んだだけで切れないよう POST（permissions-api.md 4-1 の #7） --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">ログアウト</button>
            </form>
        </div>
    </header>
    <main class="main main--scroll">
        @if (session('status'))
            {{-- 操作が済んだ知らせ（behavior.md 4-6）。出す操作は単位2 から --}}
            <div class="notice">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
