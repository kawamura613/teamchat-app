// 画面側で動くのはここだけ（behavior.md 4-1 / questions.md 決定2）。
// 素の JavaScript 1 枚・ビルドなし・外部CDNなし。使うのは次の 4 か所だけ。
//
//   1. 文字数の表示（SC-02・SC-04・SC-06・SC-07）
//   2. その場で書き換える（F-15）。本文があった場所を入力欄に差し替える
//   3. メッセージ一覧を開いたときのスクロール（Q-16 の想定）
//   4. メッセージ1件の削除の確認（questions.md 決定11・仮）
//
// 1〜3 は無効でも同じことが別の道でできる（検査はサーバ側・編集中はURL・
// 一覧は上から表示）。4 だけは無効だと安全網が外れ、確認なしで削除される。
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // ---- 1. 文字数の表示 ----------------------------------------------------
    // 上限を超えたら赤くする（.count.is-over）。弾くのはサーバ側（screens.md 4-1）
    //   <span class="count" data-count-for="<input の id>" data-count-max="1000"
    //         data-count-suffix="文字">
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

    // ---- 2. その場で書き換える（behavior.md 4-1 の #2・4-2）-----------------
    // 鉛筆は messages.edit へのリンク。JavaScript があるときは往復せずに
    // その場で差し替える。無効なときはリンクのまま、同じ状態の画面が開く。
    // 編集中は「その1件だけ」（behavior.md 4-2）。鉛筆を続けて押したときに
    // 前の書きかけが黙って消えないよう、開く前にほかを閉じる
    var closeOthers = function (except) {
        document.querySelectorAll('[data-edit-form]').forEach(function (form) {
            if (form === except || form.hidden) {
                return;
            }

            var msg = form.closest('.msg');
            var body = msg && msg.querySelector('.msg__body');

            form.hidden = true;

            if (body) {
                body.hidden = false;
            }
        });
    };

    var parts = function (element) {
        var msg = element.closest('.msg');

        return {
            form: msg && msg.querySelector('[data-edit-form]'),
            body: msg && msg.querySelector('.msg__body'),
        };
    };

    document.querySelectorAll('[data-edit-open]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var found = parts(link);

            if (!found.form || !found.body) {
                return;
            }

            event.preventDefault();
            closeOthers(found.form);
            found.body.hidden = true;
            found.form.hidden = false;

            var input = found.form.querySelector('textarea');

            if (input) {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });
    });

    document.querySelectorAll('[data-edit-cancel]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var found = parts(link);

            // URL（messages.edit）で開いた状態のときは、リンクのまま元の画面へ戻す。
            // ここで閉じるだけだと、編集中でない画面に /edit の URL が残る
            if (!found.form || !found.body || found.form.dataset.editForm === 'open') {
                return;
            }

            event.preventDefault();
            found.form.hidden = true;
            found.body.hidden = false;

            var input = found.form.querySelector('textarea');

            if (input) {
                // 元の本文に戻す。何も保存しない（screens.md 4-6）
                input.value = input.defaultValue;
                input.dispatchEvent(new Event('input'));
            }
        });
    });

    // ---- 3. 一覧を開いたときのスクロール（behavior.md 4-4）------------------
    // フラグメント付きで開いたときは何もしない。ブラウザが自分で位置を合わせる
    var list = document.querySelector('.msg-list');

    if (list && !window.location.hash) {
        list.scrollTop = list.scrollHeight;
    }

    // ---- 4. 削除の確認（questions.md 決定11・仮）----------------------------
    // 画面（SC番号・URL）は増やさない。自前のモーダルも作らない
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
