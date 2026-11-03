{{-- SC-08 検索結果（F-20 / screens.md 3-5 の SC-08・mockup/search.html）

     **1 枚で 3 つの状態を持つ**（screens.md 3-5）。
       まだ検索していない … `q` が付いていない（questions.md 決定9）
       0 件               … エラーではなく空状態。文言にキーワードが入る
       1 件以上           … 件数は**並べた行数**（上限に当たれば 50 件）

     絞り込みはコントローラーの visibleTo スコープが済ませている
     （permissions-api.md 1-6）。**削除済みはそもそも取ってきていない**ので
     （questions.md 決定1 の出口2）、ここに跡地の出し分けは無い。

     繰り返しの変数を $channel・$message にしない。@extends は定義済みの変数を
     そのままレイアウトへ渡すので、サイドバーの選択中の判定とぶつかる。 --}}
@extends('layouts.app')

@section('title', '検索結果')

@section('content')
    @php
        // 戻ってきた入力は文字列とは限らない（`q[]=x` のような細工）。文字列以外は
        // 空にして、戻った画面が落ちないようにする（screens.md 4-1 の「型が合わない値」）
        $打ったキーワード = is_string($値 = old('q', $keyword ?? '')) ? $値 : '';
    @endphp
    <div class="page">
        <h1 class="h-title">メッセージを検索</h1>
        {{-- 上部バーの検索窓と同じ `GET /search?q=…` へ送る（permissions-api.md 2-2） --}}
        <form class="field @error('q') is-error @enderror" method="GET" action="{{ route('search') }}" style="margin:var(--sp-5) 0 var(--sp-6);max-width:560px">
            <div class="inline-2">
                <input class="input" type="text" name="q" value="{{ $打ったキーワード }}" maxlength="1000" placeholder="メッセージを検索" aria-label="キーワード">
                <button class="btn btn--primary" type="submit">検索</button>
            </div>
            @error('q')
                <span class="err">{{ $message }}</span>
            @enderror
        </form>
        @if ($results === null)
            {{-- まだ検索していない（決定9）。**エラー文が出ているときは空状態を
                 出さない**（screens.md 3-5。2 つ並ぶとどちらを読むのか分からない） --}}
            @unless ($errors->has('q'))
                <div class="empty">
                    <div class="empty__title">まだ検索していません</div>
                    <div class="empty__text">上の欄にキーワードを入れて「検索」を押してください。</div>
                </div>
            @endunless
        @elseif ($results->isEmpty())
            {{-- 0 件はエラーではない（screens.md 4-8）。文言は search.html の原文 --}}
            <div class="empty">
                <div class="empty__title">「{{ $keyword }}」に一致するメッセージはありません</div>
                <div class="empty__text">別のキーワードで試すか、キーワードを短くしてください。</div>
            </div>
        @else
            <p class="t-caption" style="margin-bottom:var(--sp-3)">「{{ $keyword }}」の検索結果　{{ $results->count() }}件</p>
            <div class="rows">
                @foreach ($results as $hit)
                    {{-- **行そのものがリンク**（screens.md 3-5）。行き先と着地のアンカーは
                         behavior.md 4-4。返信は SC-06 の一覧に並ばないので、親のスレッドへ
                         送る --}}
                    <a class="row" href="{{ ($hit->parent_id === null ? route('channels.show', $hit->channel_id) : route('messages.show', $hit->parent_id)).'#message-'.$hit->id }}">
                        <div class="row__main">
                            <div class="result__meta">
                                {{-- 記号（`#`／鍵）は部品 1 つに閉じてある（screens.md 3-1） --}}
                                <span class="result__ch">@include('channels.partials.sign', ['channel' => $hit->channel]) {{ $hit->channel->name }}</span>
                                <span>·</span>
                                <span>{{ $hit->user->name }}</span>
                                {{-- 画面に出すのは created_at。編集しても動かない（data.md 2-4） --}}
                                <span>·</span>
                                <span>{{ $hit->created_at->format('Y/m/d H:i') }}</span>
                            </div>
                            {{-- 抜粋と強調は App\Services\SearchExcerpt の 1 か所。HtmlString を
                                 返すので `{{ }}` のままで `<mark>` が出る（`{!! !!}` を書かない） --}}
                            <div class="t-body">{{ \App\Services\SearchExcerpt::make($hit->body, $keyword) }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
