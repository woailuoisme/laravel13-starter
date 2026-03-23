<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique()->comment('显式名称');
            $table->string('username')->unique()->nullable()->comment('登录用户名');
            $table->string('email')->unique()->comment('电子邮箱');
            $table->string('phone')->nullable()->unique()->comment('手机号');
            $table->string('avatar')->nullable()->comment('头像');
            $table->date('birthday')->nullable()->comment('生日');
            $table->string('gender')->default('unknown')->comment('性别: male, female, unknown');
            $table->string('bio')->nullable()->comment('个人简介');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('open_id')->nullable()->unique()->comment('微信 openid');
            $table->string('github_id')->nullable()->unique()->comment('GitHub ID');
            $table->string('google_id')->nullable()->unique()->comment('Google ID');
            $table->string('nickname')->nullable()->unique()->comment('昵称');
            $table->string('telephone', 20)->nullable()->unique()->comment('手机号');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->comment('最后登录IP');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
