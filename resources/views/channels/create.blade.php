{{-- SC-04 チャンネルを作る（F-07 / screens.md 3-5 の SC-04）
     入力チェックと文言は screens.md 4-4。エラーのときは欄の下の左にエラー文、
     右に文字数（channel-new.html の形）。打った内容は残す。 --}}
@extends('layouts.app')

@section('title', 'チャンネルを作る')

@section('content')
    @php
        // 戻ってきた入力は文字列とは限らない（name[]=x のような細工）。文字列以外は
        // 空にして、戻った画面が落ちないようにする（screens.md 4-1 の「型が合わない値」）
        $打った名前 = is_string($値 = old('name', '')) ? $値 : '';
        $打った説明 = is_string($値 = old('description', '')) ? $値 : '';
    @endphp
    <div class="page" style="max-width:680px">
        <h1 class="h-title" style="margin-bottom:var(--sp-6)">チャンネルを作る</h1>
        <div class="card">
            <div class="card__body">
                <form method="POST" action="{{ route('channels.store') }}">
                    @csrf
                    <div class="field @error('name') is-error @enderror">
                        <label class="label" for="name">チャンネル名<span class="req">*</span></label>
                        <input class="input" id="name" type="text" name="name" value="{{ $打った名前 }}">
                        <div class="field-foot">
                            @error('name')
                                <span class="err">{{ $message }}</span>
                            @else
                                <span class="help"></span>
                            @enderror
                            <span class="count" data-count-for="name" data-count-max="50">{{ mb_strlen($打った名前) }} / 50</span>
                        </div>
                    </div>
                    <div class="field @error('description') is-error @enderror">
                        <label class="label" for="description">説明</label>
                        <textarea class="textarea" id="description" name="description">{{ $打った説明 }}</textarea>
                        <div class="field-foot">
                            @error('description')
                                <span class="err">{{ $message }}</span>
                            @else
                                <span class="help">何について話す場所かを短く書きます。</span>
                            @enderror
                            <span class="count" data-count-for="description" data-count-max="200">{{ mb_strlen($打った説明) }} / 200</span>
                        </div>
                    </div>
                    <div class="field @error('is_private') is-error @enderror">
                        <span class="label">公開範囲</span>
                        {{-- 選んでいる側の強調は :has(input:checked) で出す（behavior.md 4-1） --}}
                        <div class="radio-set">
                            {{-- 既定は「公開」。ただし公開範囲のエラーが出ているときは
                                 どちらも選んでいない形に戻す（「選択してください」と
                                 出ているのに選択済みに見えるのを避ける） --}}
                            <label class="radio">
                                <input type="radio" name="is_private" value="0" @checked(old('is_private', $errors->has('is_private') ? null : '0') === '0')>
                                <span><span class="rt">公開</span><span class="rd" style="display:block">ログインしている社員なら誰でも読み書きできます。</span></span>
                            </label>
                            <label class="radio">
                                <input type="radio" name="is_private" value="1" @checked(old('is_private') === '1')>
                                <span><span class="rt">プライベート</span><span class="rd" style="display:block">追加された人だけが読み書きできます。作成者がメンバーを管理します。</span></span>
                            </label>
                        </div>
                        @error('is_private')
                            <span class="err">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inline-2" style="justify-content:flex-end;margin-top:var(--sp-6)">
                        <a class="btn" href="{{ route('channels.index') }}">キャンセル</a>
                        <button class="btn btn--primary" type="submit">作成する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
