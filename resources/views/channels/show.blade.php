{{-- SC-06 チャンネル（screens.md 3-3）。中身は channels.partials.main にある。
     **SC-07（messages.show）が同じ部品を通す**ので、片方だけ直して食い違う
     ことがない（screens.md 1-1「左の本文側は同じものを出します」）。

     1件だけ編集中にして開く入口は MessageController@edit（behavior.md 4-2）。 --}}
@extends('layouts.app')

@section('title', $channel->name)

{{-- .main--scroll を外す（この画面だけページごと送らない） --}}
@section('main-modifier', '')

@section('content')
    @include('channels.partials.main')
@endsection
