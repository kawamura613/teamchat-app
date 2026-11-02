{{-- SC-09 メンバー管理（screens.md 3-4 / mockup/members.html）。
     プライベートチャンネルの作成者だけが開ける。公開チャンネルの ID で来たら
     404（permissions-api.md 1-4 の △1。判定は ChannelPolicy@manageMembers）。 --}}
@extends('layouts.app')

@section('title', 'メンバー管理')

@section('content')
    @php
        // 戻ってきた入力は文字列とは限らない（member[]=x のような細工）。
        // 文字列以外は空にして、戻った画面が落ちないようにする（screens.md 4-1）
        $打った探す値 = is_string($値 = old('member', '')) ? $値 : '';
    @endphp
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="h-title" style="display:flex;align-items:center;gap:8px">
                    @include('channels.partials.sign'){{ $channel->name }}@include('channels.partials.badge')
                </h1>
                {{-- 説明と固定文を中黒でつなぐ。中黒は全角スペースで囲む（screens.md 5） --}}
                <p class="t-caption" style="margin-top:4px">@if ($channel->description){{ $channel->description }}　·　@endif追加された人だけが読み書きできます。</p>
            </div>
            <a class="btn btn--sm" href="{{ route('channels.show', $channel) }}">チャンネルを開く</a>
        </div>
        <div class="card" style="margin-bottom:var(--sp-6)">
            <div class="card__body">
                <h2 class="h-heading" style="margin-bottom:var(--sp-3)">メンバーを追加する</h2>
                <form method="POST" action="{{ route('channels.members.store', $channel) }}">
                    @csrf
                    {{-- 打ちながら候補は出さない。ぴたり一致で1人だけ（Q-14） --}}
                    <div class="field @error('member') is-error @enderror" style="margin-bottom:0;max-width:520px">
                        <div class="inline-2">
                            <input class="input" id="member" type="text" name="member" value="{{ $打った探す値 }}" placeholder="表示名またはメールアドレスで探す">
                            <button class="btn btn--primary" type="submit"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M8 3.5v9M3.5 8h9"/></svg>追加</button>
                        </div>
                        {{-- エラー文は探す欄の下、カードの中（screens.md 4-7） --}}
                        @error('member')
                            <span class="err">{{ $message }}</span>
                        @enderror
                    </div>
                </form>
            </div>
        </div>
        {{-- 人数は中の行数と一致させる（screens.md 3-4 / 件数は「◯人」） --}}
        <h2 class="h-heading" style="margin-bottom:var(--sp-3)">メンバー　{{ $members->count() }}人</h2>
        <div class="rows">
            @foreach ($members as $member)
                <div class="row">
                    @include('partials.avatar', ['user' => $member])
                    <div class="row__main">
                        <div class="row__title">{{ $member->name }}@cannot('removeMember', [$channel, $member])<span class="badge badge--owner">作成者</span>@endcannot</div>
                        <div class="row__sub">{{ $member->email }}</div>
                    </div>
                    <div class="row__acts">
                        {{-- 外せるかは ChannelPolicy@removeMember に聞く。ここに
                             条件を書き写すと物差しが 2 つになり、△2 が育ったときに
                             ボタンだけ出続けて押すたび 403 になる（CLAUDE.md） --}}
                        @cannot('removeMember', [$channel, $member])
                            {{-- 作成者は外せない。押せない状態で残し、非表示にはしない
                                 （回答4 / permissions-api.md 1-4 の △2） --}}
                            <span class="t-caption">作成者は外せません</span>
                            <button class="btn btn--sm" type="button" disabled>外す</button>
                        @else
                            <form method="POST" action="{{ route('channels.members.destroy', [$channel, $member]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn--sm btn--danger" type="submit">外す</button>
                            </form>
                        @endcannot
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
