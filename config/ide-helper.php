<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

return [
    /*
     |--------------------------------------------------------------------------
     | Filename
     |--------------------------------------------------------------------------
     |
     | The default filename.
     |
     */

    'filename' => '_ide_helper.php',

    /*
     |--------------------------------------------------------------------------
     | Models filename
     |--------------------------------------------------------------------------
     |
     | The default filename for the models helper file.
     |
     */

    'models_filename' => '_ide_helper_models.php',

    /*
     |--------------------------------------------------------------------------
     | PhpStorm meta filename
     |--------------------------------------------------------------------------
     |
     | PhpStorm also supports the directory `.phpstorm.meta.php/` with arbitrary
     | files in it, should you need additional files for your project; e.g.
     | `.phpstorm.meta.php/laravel_ide_Helper.php'.
     |
     */
    'meta_filename' => '.phpstorm.meta.php',

    /*
     |--------------------------------------------------------------------------
     | Fluent helpers
     |--------------------------------------------------------------------------
     |
     | Set to true to generate commonly used Fluent methods.
     |
     */

    'include_fluent' => false,

    /*
     |--------------------------------------------------------------------------
     | Factory builders
     |--------------------------------------------------------------------------
     |
     | Set to true to generate factory generators for better factory()
     | method auto-completion.
     |
     | Deprecated for Laravel 8 or latest.
     |
     */

    'include_factory_builders' => false,

    /*
     |--------------------------------------------------------------------------
     | Write model magic methods
     |--------------------------------------------------------------------------
     |
     | Set to false to disable write magic methods of model.
     |
     */

    'write_model_magic_where' => true,

    /*
     |--------------------------------------------------------------------------
     | Write model external Eloquent builder methods
     |--------------------------------------------------------------------------
     |
     | Set to false to disable write external Eloquent builder methods.
     |
     */

    'write_model_external_builder_methods' => true,

    /*
     |--------------------------------------------------------------------------
     | Write model relation count and exists properties
     |--------------------------------------------------------------------------
     |
     | Set to false to disable writing of relation count and exists properties
     | to model DocBlocks.
     |
     */

    'write_model_relation_count_properties' => true,
    'write_model_relation_exists_properties' => false,

    // 必须为 false。若为 true 会直接修改 vendor 目录下的框架核心文件，这不仅破坏包的完整性，
    // 且在执行 composer install 重新拉取 vendor 后会自动失效。
    'write_eloquent_model_mixins' => false,

    /*
     |--------------------------------------------------------------------------
     | Helper files to include
     |--------------------------------------------------------------------------
     |
     | Include helper files. By default not included, but can be toggled with the
     | -- helpers (-H) option. Extra helper files can be included.
     |
     */

    'include_helpers' => false,

    'helper_files' => [
        base_path().'/vendor/laravel/framework/src/Illuminate/Support/helpers.php',
        base_path().'/vendor/laravel/framework/src/Illuminate/Foundation/helpers.php',
    ],

    /*
     |--------------------------------------------------------------------------
     | Model locations to include
     |--------------------------------------------------------------------------
     |
     | Define in which directories the ide-helper:models command should look
     | for models.
     |
     | glob patterns are supported to easier reach models in sub-directories,
     | e.g. `app/Services/* /Models` (without the space).
     |
     */

    'model_locations' => [
        'app/Models',
    ],

    /*
     |--------------------------------------------------------------------------
     | Models to ignore
     |--------------------------------------------------------------------------
     |
     | Define which models should be ignored.
     |
     */

    'ignored_models' => [
        // App\MyModel::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | Models hooks
     |--------------------------------------------------------------------------
     |
     | Define which hook classes you want to run for models to add custom information.
     |
     | Hooks should implement Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface.
     |
     */

    'model_hooks' => [
        // App\Support\IdeHelper\MyModelHook::class
    ],

    /*
     |--------------------------------------------------------------------------
     | Extra classes
     |--------------------------------------------------------------------------
     |
     | These implementations are not really extended, but called with magic functions.
     |
     */

    'extra' => [
        'Eloquent' => ['Illuminate\Database\Eloquent\Builder', 'Illuminate\Database\Query\Builder'],
        'Session' => ['Illuminate\Session\Store'],
    ],

    'magic' => [],

    /*
     |--------------------------------------------------------------------------
     | Interface implementations
     |--------------------------------------------------------------------------
     |
     | These interfaces will be replaced with the implementing class. Some interfaces
     | are detected by the helpers, others can be listed below.
     |
     */

    'interfaces' => [
        // App\MyInterface::class => App\MyImplementation::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | Support for camel cased models
     |--------------------------------------------------------------------------
     |
     | There are some Laravel packages (such as Eloquence) that allow for accessing
     | Eloquent model properties via camel case, instead of snake case.
     |
     | Enabling this option will support these packages by saving all model
     | properties as camel case, instead of snake case.
     |
     | For example, normally you would see this:
     |
     |  * @property \Illuminate\Support\Carbon $created_at
     |  * @property \Illuminate\Support\Carbon $updated_at
     |
     | With this enabled, the properties will be this:
     |
     |  * @property \Illuminate\Support\Carbon $createdAt
     |  * @property \Illuminate\Support\Carbon $updatedAt
     |
     | Note, it is currently an all-or-nothing option.
     |
     */
    'model_camel_case_properties' => false,

    /*
     |--------------------------------------------------------------------------
     | Property casts
     |--------------------------------------------------------------------------
     |
     | Cast the given "real type" to the given "type".
     |
     */
    'type_overrides' => [
        'integer' => 'int',
        'boolean' => 'bool',
    ],

    /*
     |--------------------------------------------------------------------------
     | Include DocBlocks from classes
     |--------------------------------------------------------------------------
     |
     | Include DocBlocks from classes to allow additional code inspection for
     | magic methods and properties.
     |
     */
    'include_class_docblocks' => false,

    /*
     |--------------------------------------------------------------------------
     | Force FQN usage
     |--------------------------------------------------------------------------
     |
     | Use the fully qualified (class) name in DocBlocks,
     | even if the class exists in the same namespace
     | or there is an import (use className) of the class.
     |
     */
    'force_fqn' => true,

    // 必须为 true。启用泛型声明（如 Collection<User>），能让 PHPStan (Larastan)
    // 以及 IDE 强类型解析器准确推断集合内对象类型，有效消除遍历过程中的虚假类型报错。
    'use_generics_annotations' => true,

    /*
     |--------------------------------------------------------------------------
     | Default return types for macros
     |--------------------------------------------------------------------------
     |
     | Define default return types for macros without explicit return types.
     | e.g. `\Illuminate\Database\Query\Builder::class => 'static'`,
     |      `\Illuminate\Support\Str::class => 'string'`
     |
     */
    'macro_default_return_types' => [
        Factory::class => PendingRequest::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | Additional relation types
     |--------------------------------------------------------------------------
     |
     | Sometimes it's needed to create custom relation types. The key of the array
     | is the relationship method name. The value of the array is the fully-qualified
     | class name of the relationship, e.g. `'relationName' => RelationShipClass::class`.
     |
     */
    'additional_relation_types' => [],

    /*
     |--------------------------------------------------------------------------
     | Additional relation return types
     |--------------------------------------------------------------------------
     |
     | When using custom relation types its possible for the class name to not contain
     | the proper return type of the relation. The key of the array is the relationship
     | method name. The value of the array is the return type of the relation ('many'
     | or 'morphTo').
     | e.g. `'relationName' => 'many'`.
     |
     */
    'additional_relation_return_types' => [],

    /*
     |--------------------------------------------------------------------------
     | Enforce nullable Eloquent relationships on not null columns
     |--------------------------------------------------------------------------
     |
     | When set to true (default), this option enforces nullable Eloquent relationships.
     | However, in cases where the application logic ensures the presence of related
     | records it may be desirable to set this option to false to avoid unwanted null warnings.
     |
     | Default: true
     | A not null column with no foreign key constraint will have a "nullable" relationship.
     |  * @property int $not_null_column_with_no_foreign_key_constraint
     |  * @property-read BelongsToVariation|null $notNullColumnWithNoForeignKeyConstraint
     |
     | Option: false
     | A not null column with no foreign key constraint will have a "not nullable" relationship.
     |  * @property int $not_null_column_with_no_foreign_key_constraint
     |  * @property-read BelongsToVariation $notNullColumnWithNoForeignKeyConstraint
     |
     */

    'enforce_nullable_relationships' => true,

    // 保持空置。请勿解开注释！否则每次 migrate 之后会自动复写 _ide_helper_models.php，
    // 进而破坏并覆盖我们为 IDE 特别配置的“双轨制”正则清洗后处理逻辑，导致 IDE 重新报未实现接口的红线。
    'post_migrate' => [
        // 'ide-helper:models --nowrite',
    ],
];
