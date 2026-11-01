{{-- SC-01 ログイン（F-02 / screens.md 3-5 の SC-01 の差分表）
     入力欄はメールアドレスとパスワードの2つだけ。必須印・ヘルプ文・文字数表示は無い。
     「パスワードをお忘れの方」は作らない（features.md 2-3）。 --}}
@extends('layouts.auth')

@section('title', 'ログイン')

@section('form')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="field @error('email') is-error @enderror">
            <label class="label" for="email">メールアドレス</label>
            <input class="input" id="email" type="text" name="email" value="{{ old('email') }}">
            @error('email')
                <span class="err">{{ $message }}</span>
            @enderror
        </div>
        <div class="field @error('password') is-error @enderror">
            <label class="label" for="password">パスワード</label>
            <input class="input" id="password" type="password" name="password">
            @error('password')
                <span class="err">{{ $message }}</span>
            @enderror
        </div>
        <button class="btn btn--primary btn--lg btn--block" type="submit">ログイン</button>
    </form>
@endsection

@section('foot')
    アカウントをお持ちでない方は <a href="{{ route('register') }}">新規登録</a>
@endsection
