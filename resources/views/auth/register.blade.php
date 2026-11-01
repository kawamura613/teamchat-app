{{-- SC-02 新規登録（F-01 / screens.md 3-2）
     エラーのときは文字数表示の左側にエラー文が入り、打った内容は残す（パスワードの欄を除く）。 --}}
@extends('layouts.auth')

@section('title', '新規登録')

@section('form')
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="field @error('name') is-error @enderror">
            <label class="label" for="name">表示名<span class="req">*</span></label>
            <input class="input" id="name" type="text" name="name" value="{{ old('name') }}">
            <div class="field-foot">
                @error('name')
                    <span class="err">{{ $message }}</span>
                @else
                    <span class="help">メッセージに表示される名前です。</span>
                @enderror
                <span class="count" data-count-for="name" data-count-max="30">0 / 30</span>
            </div>
        </div>
        <div class="field @error('email') is-error @enderror">
            <label class="label" for="email">メールアドレス<span class="req">*</span></label>
            <input class="input" id="email" type="text" name="email" value="{{ old('email') }}">
            @error('email')
                <span class="err">{{ $message }}</span>
            @enderror
        </div>
        <div class="field @error('password') is-error @enderror">
            <label class="label" for="password">パスワード<span class="req">*</span></label>
            <input class="input" id="password" type="password" name="password">
            @error('password')
                <span class="err">{{ $message }}</span>
            @else
                <span class="help">8文字以上で入力してください。</span>
            @enderror
        </div>
        <div class="field @error('password_confirmation') is-error @enderror">
            <label class="label" for="password_confirmation">パスワード（確認用）<span class="req">*</span></label>
            <input class="input" id="password_confirmation" type="password" name="password_confirmation">
            @error('password_confirmation')
                <span class="err">{{ $message }}</span>
            @enderror
        </div>
        <button class="btn btn--primary btn--lg btn--block" type="submit">登録する</button>
    </form>
@endsection

@section('foot')
    すでにアカウントをお持ちの方は <a href="{{ route('login') }}">ログイン</a>
@endsection
