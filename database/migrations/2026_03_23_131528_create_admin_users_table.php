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
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();

            // 基础信息
            $table->string('username')->unique()->comment('登录用户名');
            $table->string('name')->comment('真实姓名');
            $table->string('email')->unique()->comment('邮箱地址');
            $table->string('password')->comment('加密密码');
            $table->string('phone')->nullable()->unique()->comment('手机号');

            // 状态控制
            $table->boolean('is_active')->default(true)->comment('是否启用: 0 禁用, 1 启用');

            // 审计与安全
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->ipAddress('last_login_ip')->nullable()->comment('最后登录IP');
            $table->string('avatar_url')->nullable()->comment('头像连接');

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes()->comment('软删除时间');

            // 常用索引
            $table->index(['username', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
