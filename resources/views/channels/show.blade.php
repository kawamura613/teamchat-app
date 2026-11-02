{{-- SC-06 チャンネル（screens.md 3-3 の「チャンネルの見出し」）
     単位2 では見出しだけ。メッセージ一覧（.msg-list）・投稿欄（.composer）・
     ホバー操作は単位4。一覧が縦スクロールを持つのは単位4 なので、それまでは
     レイアウトの .main--scroll でページごと送る。 --}}
@extends('layouts.app')

@section('title', $channel->name)

@section('content')
    <div class="ch-head">
        <div class="ch-head__main">
            <div class="ch-head__name"><span class="sig">@include('channels.partials.sign')</span>{{ $channel->name }}</div>
            @include('channels.partials.badge')
            @if ($channel->description)
                <span class="ch-head__desc">{{ $channel->description }}</span>
            @endif
        </div>
        @can('update', $channel)
            <div class="ch-head__acts">
                <a class="btn btn--sm" href="{{ route('channels.edit', $channel) }}"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M11 2.6 13.4 5 5.4 13H3v-2.4z"/></svg>編集</a>
                <a class="btn btn--sm btn--danger" href="{{ route('channels.delete', $channel) }}"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.6 8.5h5.8l.6-8.5"/></svg>削除</a>
            </div>
        @endcan
    </div>
@endsection
