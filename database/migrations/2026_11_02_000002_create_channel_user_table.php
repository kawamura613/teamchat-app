<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * channel_user（data.md 2-3・2-5）。
     *
     * プライベートチャンネルだけ行を作る（data.md 1-3 の決定2）。1行の意味は
     * 「この人はこのチャンネルを見られる」。日時の列は持たない（画面に出す
     * ところがなく、belongsToMany を素の形で扱えるため）。
     */
    public function up(): void
    {
        Schema::create('channel_user', function (Blueprint $table) {
            // この順がそのまま「追加された順」になる（data.md 2-3）
            $table->id();
            // チャンネルを消すとメンバーの情報も消える（data.md 2-5）
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // 同じ人を二重に追加できない（data.md 2-5）
            $table->unique(['channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_user');
    }
};
