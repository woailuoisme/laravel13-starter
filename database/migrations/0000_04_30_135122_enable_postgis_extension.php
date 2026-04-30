<?php

use Clickbar\Magellan\Schema\MagellanSchema;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        MagellanSchema::enablePostgisIfNotExists($this->connection);
        // 3. 启用 uuid-ossp 扩展（生成 UUID）
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "vector"');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // 回滚顺序应与 up 相反（但扩展删除无严格要求）
        // 1. 删除 vector 扩展（如果存在）
        MagellanSchema::disablePostgisIfExists($this->connection);
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
        DB::statement('DROP EXTENSION IF EXISTS "vector"');
    }
};
