<?php

namespace App\Models;

use Framework\Database\Model;

/**
 * 用户模型
 */
class User extends Model
{
    /**
     * 表名
     */
    protected string $table = 'users';

    /**
     * 允许批量赋值的字段
     */
    protected array $fillable = [
        'username',
        'email',
        'password',
    ];

    /**
     * 隐藏字段（序列化时不包含）
     */
    protected array $hidden = [
        'password',
    ];
}
