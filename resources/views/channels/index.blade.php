{{-- SC-03 チャンネル一覧（F-06 / screens.md 3-5 の SC-03）
     出す行は「公開チャンネル全部＋自分がメンバーのプライベート」。絞り込みは
     コントローラーの visibleTo スコープが済ませている（permissions-api.md 1-6）。
     繰り返しの変数を $channel にしない。@extends は定義済みの変数をそのまま
     レイアウトへ渡すので、サイドバーの選択中の判定とぶつかる。 --}}
@extends('layouts.app')

@section('title', 'チャンネル一覧')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="h-title">チャンネル一覧</h1>
                <p class="t-caption" style="margin-top:4px">公開チャンネルは、ログインしている社員なら誰でも読み書きできます。</p>
            </div>
            <a class="btn btn--primary" href="{{ route('channels.create') }}"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8 3.5v9M3.5 8h9"/></svg>チャンネルを作る</a>
        </div>
        @if ($channels->isEmpty())
            {{-- 0件のとき（T-2-18 / screens.md 3-5。文言は未確定・questions.md Q-35） --}}
            <div class="empty">
                <div class="empty__title">まだチャンネルがありません</div>
                <div class="empty__text">チャンネルを作って会話を始めましょう</div>
            </div>
        @else
            <div class="rows">
                @foreach ($channels as $listed)
                    <div class="row">
                        <div class="row__main">
                            <div class="row__title"><span class="sig">@include('channels.partials.sign', ['channel' => $listed])</span>{{ $listed->name }}@include('channels.partials.badge', ['channel' => $listed])</div>
                            <div class="row__sub">@if ($listed->description){{ $listed->description }}　·　@endif作成者：{{ $listed->creator->name }}</div>
                        </div>
                        <div class="row__acts">
                            {{-- 「メンバー」はプライベート×作成者の行だけ
                                 （mockup/channels.html / permissions-api.md 1-4 の △1） --}}
                            @can('manageMembers', $listed)
                                <a class="btn btn--sm" href="{{ route('channels.members.index', $listed) }}">メンバー</a>
                            @endcan
                            @can('update', $listed)
                                <a class="btn btn--sm" href="{{ route('channels.edit', $listed) }}">編集</a>
                            @endcan
                            <a class="btn btn--sm" href="{{ route('channels.show', $listed) }}">開く</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
