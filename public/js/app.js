// 画面側で動くのはここだけ（behavior.md 4-1）。素の JavaScript・ビルドなし。
//
// いまは 1 か所だけ: 文字数の表示（#1）。
// 上限を超えたら赤くする（.count.is-over）。無効なときは静的な表示のままで、
// 検査はサーバ側で必ず行う（screens.md 4-1）。
//
// 単位2（チャンネル名・説明）と単位4（メッセージ本文）も同じ形で乗せられる:
//   <span class="count" data-count-for="<input の id>" data-count-max="1000" data-count-suffix="文字">
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-count-for]').forEach(function (output) {
        var input = document.getElementById(output.dataset.countFor);

        if (!input) {
            return;
        }

        var max = Number(output.dataset.countMax);
        var suffix = output.dataset.countSuffix ? ' ' + output.dataset.countSuffix : '';

        var render = function () {
            var length = Array.from(input.value).length;

            output.textContent = length.toLocaleString() + ' / ' + max.toLocaleString() + suffix;
            output.classList.toggle('is-over', length > max);
        };

        input.addEventListener('input', render);
        render();
    });
});
