{{-- アバター（screens.md 3-3 / Q-36 の想定）。表示名の先頭2文字。
     色は人ごとに固定の4色（design-guide.md 1）。id から機械的に割り当てるので、
     同じ人にはいつも同じ色が出る。既定・--b・--c・--d の順に並べると、初期データの
     3人（佐藤・鈴木・高橋）がモックアップと同じ色になる。 --}}
@php
    $色 = ['', ' avatar--b', ' avatar--c', ' avatar--d'][($user->id - 1) % 4];
@endphp
<span class="avatar{{ $色 }}">{{ mb_substr($user->name, 0, 2) }}</span>
