{{-- SC-05 チャンネルを編集する（F-08 / screens.md 3-5 の SC-05）
     公開範囲の欄はない。代わりにチャンネル名の下にヘルプを出す（questions.md Q-09）。
     下半分の赤枠は SC-10 への入口で、この画面では消えない。 --}}
@extends('layouts.app')

@section('title', 'チャンネルを編集する')

@section('content')
    <div class="page" style="max-width:680px">
        <h1 class="h-title" style="margin-bottom:var(--sp-6)">チャンネルを編集する</h1>
        <div class="card" style="margin-bottom:var(--sp-8)">
            <div class="card__body">
                <form method="POST" action="{{ route('channels.update', $channel) }}">
                    @csrf
                    @method('PATCH')
                    <div class="field @error('name') is-error @enderror">
                        <label class="label" for="name">チャンネル名<span class="req">*</span></label>
                        <input class="input" id="name" type="text" name="name" value="{{ old('name', $channel->name) }}">
                        <div class="field-foot">
                            @error('name')
                                <span class="err">{{ $message }}</span>
                            @else
                                <span class="help">{{ $channel->is_private ? 'プライベートチャンネルです。公開範囲はあとから変更できません。' : '公開チャンネルです。公開範囲はあとから変更できません。' }}</span>
                            @enderror
                            <span class="count" data-count-for="name" data-count-max="50">{{ mb_strlen(old('name', $channel->name)) }} / 50</span>
                        </div>
                    </div>
                    <div class="field @error('description') is-error @enderror">
                        <label class="label" for="description">説明</label>
                        <textarea class="textarea" id="description" name="description">{{ old('description', $channel->description) }}</textarea>
                        <div class="field-foot">
                            @error('description')
                                <span class="err">{{ $message }}</span>
                            @else
                                <span class="help"></span>
                            @enderror
                            <span class="count" data-count-for="description" data-count-max="200">{{ mb_strlen(old('description', $channel->description ?? '')) }} / 200</span>
                        </div>
                    </div>
                    <div class="inline-2" style="justify-content:flex-end;margin-top:var(--sp-6)">
                        <a class="btn" href="{{ route('channels.show', $channel) }}">キャンセル</a>
                        <button class="btn btn--primary" type="submit">保存する</button>
                    </div>
                </form>
            </div>
        </div>
        <h2 class="h-heading" style="margin-bottom:var(--sp-3)">チャンネルを削除する</h2>
        <div class="danger-box">
            <div class="spread">
                <div>
                    <div class="t-label" style="color:var(--danger)">このチャンネルを削除します</div>
                    <div class="t-caption">メッセージと返信もすべて削除され、元に戻せません。</div>
                </div>
                {{-- ここでは消えない。確認は SC-10（screens.md 1-2） --}}
                <a class="btn btn--danger" href="{{ route('channels.delete', $channel) }}">削除する</a>
            </div>
        </div>
    </div>
@endsection
