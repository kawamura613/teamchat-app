{{-- SC-07 スレッド（screens.md 3-5 / mockup/thread.html）。

     SC-06 との差は「右に 400px のパネルがあるか」だけで、左の本文側は同じものを
     出す（screens.md 1-1）。それ自身の URL を持つ画面なので、ブラウザの「戻る」で
     閉じられ、URL を人に送れる（Q-24 の想定 / permissions-api.md 2-1）。

     $parent  … 元のメッセージ。**削除済みのことがある**（決定1 の出口1-b）
     $replies … その返信。削除済みも含む（出口1-c）
     $channel・$messages・$editingId … 左の本文側に渡すぶん（SC-06 と同じ） --}}
@extends('layouts.app')

@section('title', $channel->name)

{{-- .main--scroll を外す。SC-06 と同じで、一覧（.msg-list）自身がスクロールする --}}
@section('main-modifier', '')

{{-- サイドバー｜本文｜パネル の3列にする（mockup の .app__body--thread） --}}
@section('body-modifier', 'app__body--thread')

@section('content')
    @include('channels.partials.main')
@endsection

{{-- パネルは .main の外・.app__body の中（mockup どおり） --}}
@section('aside')
    @include('messages.partials.thread')
@endsection
