<?php

namespace App\Models {
    /**
     * 此类仅为解决 PHPStan/Larastan 在解析由 Laravel IDE Helper 生成的 _ide_helper_models.php 时，
     * 将未带完全限定名称的 Builder<static> 误解析为 \App\Models\Builder 的问题。
     * 本文件已被 .gitignore 忽略，仅供静态分析期间加载使用。
     */
    class Builder extends \Illuminate\Database\Eloquent\Builder {}
}
