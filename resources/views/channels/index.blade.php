{{-- SC-03 チャンネル一覧（F-06 / screens.md 3-5 の SC-03）
     単位1 では見出しだけ。一覧の行・0件のときの枠・「チャンネルを作る」は単位2。 --}}
@extends('layouts.app')

@section('title', 'チャンネル一覧')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="h-title">チャンネル一覧</h1>
                <p class="t-caption" style="margin-top:4px">公開チャンネルは、ログインしている社員なら誰でも読み書きできます。</p>
            </div>
        </div>
    </div>
@endsection
