{{-- SC-06 チャンネル（screens.md 3-3）。上から「チャンネルの見出し」
     「メッセージ一覧（縦スクロール）」「投稿欄（下に固定）」の3段。

     一覧は .msg-list 自身が縦スクロールするので、共通の枠の .main--scroll は外す。
     1件だけ編集中にして開く入口は MessageController@edit（behavior.md 4-2）。 --}}
@extends('layouts.app')

@section('title', $channel->name)

{{-- .main--scroll を外す（この画面だけページごと送らない） --}}
@section('main-modifier', '')

@section('content')
    <div class="ch-head">
        <div class="ch-head__main">
            <div class="ch-head__name"><span class="sig">@include('channels.partials.sign')</span>{{ $channel->name }}</div>
            @include('channels.partials.badge')
            @if ($channel->description)
                <span class="ch-head__desc">{{ $channel->description }}</span>
            @endif
        </div>
        {{-- 編集は F-08、削除は F-09 で権限マトリクスの行が別（permissions-api.md 1-3） --}}
        @canany(['update', 'delete'], $channel)
            <div class="ch-head__acts">
                @can('update', $channel)
                <a class="btn btn--sm" href="{{ route('channels.edit', $channel) }}"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M11 2.6 13.4 5 5.4 13H3v-2.4z"/></svg>編集</a>
                @endcan
                @can('delete', $channel)
                <a class="btn btn--sm btn--danger" href="{{ route('channels.delete', $channel) }}"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5"/></svg>削除</a>
                @endcan
            </div>
        @endcanany
    </div>
    <div class="msg-list">
        @if ($messages->isEmpty())
            {{-- 0件のとき（screens.md 3-3 / Q-35 の想定） --}}
            <div class="empty" style="margin:0 var(--sp-6)">
                <div class="empty__title">まだメッセージがありません</div>
                <div class="empty__text">最初のメッセージを送ってみましょう</div>
            </div>
        @else
            @php
                // 日付の区切りは日付が変わる手前に1本だけ（screens.md 3-3 / 回答7）。
                // locale は en のままなので、曜日は自分の表から引く（behavior.md 3-8）
                $曜日 = ['日', '月', '火', '水', '木', '金', '土'];
                $直前の日 = null;
            @endphp
            @foreach ($messages as $行)
                @if ($行->created_at->toDateString() !== $直前の日)
                    <div class="day-sep"><span>{{ $行->created_at->format('Y年n月j日') }}（{{ $曜日[$行->created_at->dayOfWeek] }}）</span></div>
                    @php $直前の日 = $行->created_at->toDateString(); @endphp
                @endif
                @include('messages.partials.message', ['message' => $行, 'editingId' => $editingId])
            @endforeach
        @endif
        {{-- 素で開いたときの着地位置（behavior.md 4-4） --}}
        <div id="bottom"></div>
    </div>
    @php
        // 投稿欄に戻すのは、落ちたのが投稿欄のときだけ。編集中が落ちたときは
        // エラーが 'edit' の袋に入るので、ここは空のまま（editor が受け取る）。
        // $editingId で見ると、編集中のアドレスで投稿欄が落ちたときに下書きが消える
        $投稿欄の本文 = $errors->getBag('default')->has('body') && is_string($値 = old('body', '')) ? $値 : '';
    @endphp
    {{-- 投稿欄は、そのチャンネルに書ける人にだけ出す（screens.md 3-3 / F-05）。
         この画面を開けるのは列C だけなので、ここまで来た人は書ける --}}
    <div class="composer">
        <form method="POST" action="{{ route('channels.messages.store', $channel) }}">
            @csrf
            <div class="composer__box @error('body') is-error @enderror">
                <textarea class="composer__ta" id="composer-body" name="body" placeholder="#{{ $channel->name }} にメッセージを送る">{{ $投稿欄の本文 }}</textarea>
                <div class="composer__foot">
                    {{-- 欄の下は左にエラー文・右に文字数（screens.md 4-1） --}}
                    <span class="inline-3">
                        @error('body')
                            <span class="err">{{ $message }}</span>
                        @enderror
                        <span class="count" data-count-for="composer-body" data-count-max="1000" data-count-suffix="文字">{{ number_format(mb_strlen($投稿欄の本文)) }} / 1,000 文字</span>
                    </span>
                    <button class="btn btn--primary btn--sm" type="submit">送信</button>
                </div>
            </div>
        </form>
    </div>
@endsection
