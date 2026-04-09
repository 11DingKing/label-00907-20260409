<?php

namespace Framework\Validation;

/**
 * 数据验证器
 * 提供常用验证规则
 */
class Validator
{
    /**
     * 验证数据
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * 验证规则
     * @var array<string, array<string>>
     */
    private array $rules;

    /**
     * 错误信息
     * @var array<string, array<string>>
     */
    private array $errors = [];

    /**
     * 验证规则映射
     * @var array<string, callable>
     */
    private static array $ruleMap = [];

    /**
     * 注册自定义验证规则
     */
    public static function extend(string $rule, callable $callback): void
    {
        self::$ruleMap[$rule] = $callback;
    }

    /**
     * 创建验证器实例
     * 
     * @param array<string, mixed> $data 待验证数据
     * @param array<string, array<string>> $rules 验证规则
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->registerDefaultRules();
    }

    /**
     * 注册默认验证规则
     */
    private function registerDefaultRules(): void
    {
        if (!empty(self::$ruleMap)) {
            return; // 已注册
        }

        // 必填
        self::$ruleMap['required'] = function ($value) {
            return !empty($value) || $value === '0' || $value === 0;
        };

        // 字符串
        self::$ruleMap['string'] = function ($value) {
            return is_string($value);
        };

        // 整数
        self::$ruleMap['integer'] = function ($value) {
            return filter_var($value, FILTER_VALIDATE_INT) !== false;
        };

        // 数字
        self::$ruleMap['numeric'] = function ($value) {
            return is_numeric($value);
        };

        // 邮箱
        self::$ruleMap['email'] = function ($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        };

        // URL
        self::$ruleMap['url'] = function ($value) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        };

        // 最小长度
        self::$ruleMap['min'] = function ($value, $min) {
            if (is_string($value)) {
                return mb_strlen($value) >= (int) $min;
            }
            if (is_numeric($value)) {
                return $value >= (int) $min;
            }
            return false;
        };

        // 最大长度
        self::$ruleMap['max'] = function ($value, $max) {
            if (is_string($value)) {
                return mb_strlen($value) <= (int) $max;
            }
            if (is_numeric($value)) {
                return $value <= (int) $max;
            }
            return false;
        };

        // 长度范围
        self::$ruleMap['between'] = function ($value, $min, $max) {
            if (is_string($value)) {
                $len = mb_strlen($value);
                return $len >= (int) $min && $len <= (int) $max;
            }
            if (is_numeric($value)) {
                return $value >= (int) $min && $value <= (int) $max;
            }
            return false;
        };

        // 正则匹配
        self::$ruleMap['regex'] = function ($value, $pattern) {
            return preg_match($pattern, $value) === 1;
        };

        // 在列表中
        self::$ruleMap['in'] = function ($value, ...$values) {
            return in_array($value, $values);
        };

        // 不在列表中
        self::$ruleMap['not_in'] = function ($value, ...$values) {
            return !in_array($value, $values);
        };
    }

    /**
     * 执行验证
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                // 解析规则（支持参数，如 min:5）
                $ruleParts = explode(':', $rule, 2);
                $ruleName = $ruleParts[0];
                $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

                // 如果字段为空且不是 required 规则，跳过其他规则
                if (empty($value) && $ruleName !== 'required') {
                    continue;
                }

                // 执行验证
                if (!$this->validateRule($field, $value, $ruleName, $ruleParams)) {
                    $this->addError($field, $ruleName, $ruleParams);
                    break; // 一个字段一个规则失败就停止
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * 验证单个规则
     */
    private function validateRule(string $field, mixed $value, string $ruleName, array $params): bool
    {
        // 检查自定义规则
        if (isset(self::$ruleMap[$ruleName])) {
            $callback = self::$ruleMap[$ruleName];
            return $callback($value, ...$params);
        }

        // 默认返回 true（未知规则）
        return true;
    }

    /**
     * 添加错误信息
     */
    private function addError(string $field, string $rule, array $params): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $message = $this->getErrorMessage($field, $rule, $params);
        $this->errors[$field][] = $message;
    }

    /**
     * 获取错误信息
     */
    private function getErrorMessage(string $field, string $rule, array $params): string
    {
        $messages = [
            'required' => "{$field} 字段是必填的",
            'string' => "{$field} 必须是字符串",
            'integer' => "{$field} 必须是整数",
            'numeric' => "{$field} 必须是数字",
            'email' => "{$field} 必须是有效的邮箱地址",
            'url' => "{$field} 必须是有效的 URL",
            'min' => "{$field} 的最小值为 " . ($params[0] ?? ''),
            'max' => "{$field} 的最大值为 " . ($params[0] ?? ''),
            'between' => "{$field} 必须在 " . ($params[0] ?? '') . " 到 " . ($params[1] ?? '') . " 之间",
            'regex' => "{$field} 格式不正确",
            'in' => "{$field} 的值不在允许的范围内",
            'not_in' => "{$field} 的值在禁止的范围内",
        ];

        return $messages[$rule] ?? "{$field} 验证失败";
    }

    /**
     * 获取所有错误信息
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误信息
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $field => $messages) {
            if (!empty($messages)) {
                return $messages[0];
            }
        }
        return null;
    }

    /**
     * 获取指定字段的错误信息
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
