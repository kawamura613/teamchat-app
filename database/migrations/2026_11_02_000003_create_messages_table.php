<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * messages（data.md 2-4・2-5）。
     *
     * メッセージと返信を1つの表で持つ（parent_id が NULL ならメッセージ、
     * 値が入っていれば返信・data.md 1-3 の決定1）。
     *
     * 単位2 では表だけ作る。投稿・一覧・編集・削除の画面は単位4・単位5。
     * チャンネル削除の連鎖（T-2-16）と削除の確認に出す件数（T-2-14）が
     * この表を必要とするため、マイグレーションだけ先に入れる。
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // チャンネルを消すと中のメッセージ・返信も消える（data.md 2-5）
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // 親が物理削除されるのはチャンネルごと消すときだけ（data.md 2-5）
            $table->foreignId('parent_id')->nullable()->constrained('messages')->cascadeOnDelete();
            // 削除しても本文は残す（questions.md 決定1・案1）。NULL にならない
            $table->string('body', 1000);
            // NULL でなければ「編集済み」を出す。updated_at は削除でも動くので使えない
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // 古い順に並べる／返信を引く（data.md 2-5）。body には張らない
            $table->index(['channel_id', 'created_at']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
