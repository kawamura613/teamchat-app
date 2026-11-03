{{-- 共通の枠（F-04 / screens.md 3-1）。単位2 でサイドバーを足し、単位6 で
     上部バーの検索窓を足した。

     $sidebarChannels と $selectedChannelId は AppServiceProvider の
     View Composer が渡す（permissions-api.md 1-6）。各コントローラーでは渡さない。
     選択中は URL の {channel} で見るので、画面側の変数名に左右されない。 --}}
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1280">
    {{-- @yield 自体は素の echo だが、@section('title', $値) の第 2 引数は
         Factory::startSection が e() を通すので、ここは二重に包まない --}}
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
        {{-- 検索窓（F-20 / screens.md 3-1）。キーワードを入れて確定すると
             `GET /search?q=…` で SC-08 へ行く（permissions-api.md 2-2）。
             **ここには打った内容を残さない。**残すのは SC-08 の欄の役目で、
             3-1 はプレースホルダだけと決めている --}}
        <div class="topbar__search">
            <form class="search-input" method="GET" action="{{ route('search') }}" role="search">
                <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14" stroke-linecap="round"/></svg>
                <input type="text" name="q" placeholder="メッセージを検索" maxlength="1000" aria-label="メッセージを検索">
            </form>
        </div>
        <div class="topbar__me">
            <span class="nm">{{ auth()->user()->name }}</span>
            {{-- リンクを踏んだだけで切れないよう POST（permissions-api.md 4-1 の #7） --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">ログアウト</button>
            </form>
        </div>
    </header>
    {{-- SC-07 だけ app__body--thread で3列（サイドバー｜本文｜パネル）にする --}}
    <div class="app__body @yield('body-modifier')">
        <nav class="sidebar">
            <div class="side-group">チャンネル</div>
            @foreach ($sidebarChannels as $sidebarChannel)
                <a class="side-item{{ (string) $sidebarChannel->id === (string) $selectedChannelId ? ' is-selected' : '' }}" href="{{ route('channels.show', $sidebarChannel) }}"><span class="sig">@include('channels.partials.sign', ['channel' => $sidebarChannel])</span><span class="nm">{{ $sidebarChannel->name }}</span></a>
            @endforeach
            <div class="side-foot">
                <a href="{{ route('channels.index') }}">チャンネル一覧を見る</a>
            </div>
        </nav>
        {{-- 既定はページごと縦に送る。SC-06 だけは一覧（.msg-list）自身が
             スクロールするので、画面側から外せるようにしてある --}}
        <main class="main @yield('main-modifier', 'main--scroll')">
            @if (session('status'))
                {{-- 操作が済んだ知らせ（behavior.md 4-6）。次の遷移で消える --}}
                <div class="notice">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
        {{-- スレッドのパネルは .main の外・.app__body の中（mockup/thread.html
             どおり）。SC-07 以外は何も出ない --}}
        @yield('aside')
    </div>
</div>
</body>
</html>
