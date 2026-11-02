<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * channels（data.md 2-2・2-5）。
     *
     * name に一意制約は張らない。張ると、見えないはずのプライベートチャンネルと
     * 名前がぶつかったときに「すでにあります」と断ることになり、存在が漏れる
     * （data.md 4-3）。重複はその人が見られる範囲だけでアプリ側が見る。
     */
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('description', 200)->nullable();
            // 既定値を置かない。作成時に必ず決まる値（data.md 2-2）
            $table->boolean('is_private');
            // 利用者が消える経路がないので制限（data.md 2-5）
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // 重複検査で引く。一意制約ではない（data.md 2-5）
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
