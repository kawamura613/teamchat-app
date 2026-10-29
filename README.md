# チームチャットアプリ 開発用リポジトリ

Tutorial 16「総仕上げ」で、**あなたが一本のアプリを作り切る**ための出発点です。

## 入っているもの

| | 中身 |
|:--|:--|
| `docs/spec.md` | **仕様書**。クライアントからの要求を文章にしたもの |
| `mockup/` | **画面見本**。`index.html` をブラウザで開くと全10画面を見られます |
| `docs/design/` | **設計書一式の置き場**。いまは空です。ここにあなたが設計を作ります（構成は `docs/design/README.md`） |
| Laravel と Sail | 素のLaravelと、Dockerで動かすための設定 |

## 入っていないもの

**アプリの機能のコードは、1行も入っていません。**画面もテーブルも、これから設計して、AIと一緒に作ります。

---

## 使いはじめる

1. GitHubのこのページで「**Use this template**」→「Create a new repository」から、**自分のリポジトリ**を作ります
2. 作ったリポジトリを `~/laravel-practice/` の下に clone します

```bash
cd ~/laravel-practice
git clone <自分のリポジトリのURL> teamchat-app
cd teamchat-app
```

---

## 初回起動（5手）

> ⚠️ **先に、80番ポートと3306番ポートを使っている別のアプリを止めてください。**`post-app-practice` などを動かしたままだと、**3手目**（`sail up -d`）で `Bind for 0.0.0.0:80 failed: port is already allocated` と言われて起動しません。そのフォルダで `./vendor/bin/sail stop` を実行してから始めてください。

**1. ライブラリを取ってくる**

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer install
```

**2. 環境変数のファイルを作る**

```bash
cp .env.example .env
```

**3. コンテナを起動する**

```bash
./vendor/bin/sail up -d
```

**そのあと30秒ほど待ってください。**コンテナが `Up` になっても、データベースはまだ中で立ち上がっている途中です。待たずに次へ進むと、5手目でこうなります。

```
SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, ...)
```

`./vendor/bin/sail ps` の `mysql` の欄が `Up (healthy)` に変われば、確実に繋がります（手元では30秒でした）。**この文言が出たときは、少し待って同じコマンドをもう一度打てば通ります。**

**4. アプリケーションキーを作る**

```bash
./vendor/bin/sail artisan key:generate
```

**5. テーブルを作る**

```bash
./vendor/bin/sail artisan migrate
```

ブラウザで <http://localhost> を開いて、Laravelの初期画面が出れば準備完了です。

> 💡 5手目に `--seed` は付けません。初期データは、これから機能を作りながら自分で積んでいきます。

---

## 止める・片づける

```bash
./vendor/bin/sail stop      # 止めるだけ（データは残ります）
./vendor/bin/sail down -v   # 止めて、データベースの中身ごと消す
```

別のアプリを動かしたいときは `sail stop` で止めてください（ポートが空きます）。`down -v` のあとは、3手目（`sail up -d`）からやり直します。

---

## フォルダの歩き方

```
teamchat-app/
├── docs/
│   ├── spec.md          仕様書（変えない。疑問は確認事項へ）
│   └── design/          設計書一式（あなたが作る）
├── mockup/
│   └── index.html       ここから全10画面を見る
└── （素のLaravel一式）
```

まず `mockup/index.html` をブラウザで開いて、どんなアプリを作るのかを目で見てから、`docs/spec.md` を読んでください。
