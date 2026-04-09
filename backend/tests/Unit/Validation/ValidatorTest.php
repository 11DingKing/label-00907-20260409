<?php

namespace Tests\Unit\Validation;

use Framework\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Validator（验证器）测试用例
 */
class ValidatorTest extends TestCase
{
    /**
     * 测试必填验证
     */
    public function testRequired(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => ['required']];
        
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->errors());
    }

    /**
     * 测试字符串验证
     */
    public function testString(): void
    {
        $data = ['name' => 123];
        $rules = ['name' => ['string']];
        
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
    }

    /**
     * 测试整数验证
     */
    public function testInteger(): void
    {
        $data = ['age' => '25'];
        $rules = ['age' => ['integer']];
        
        $validator = new Validator($data, $rules);
        // '25' 字符串会被 filter_var 验证为整数
        $this->assertTrue($validator->validate());
    }

    /**
     * 测试邮箱验证
     */
    public function testEmail(): void
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => ['email']];
        
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
        
        $data = ['email' => 'valid@example.com'];
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
    }

    /**
     * 测试最小长度验证
     */
    public function testMin(): void
    {
        $data = ['password' => '12345']; // 5 个字符
        $rules = ['password' => ['min:6']];
        
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
        
        $data = ['password' => '123456']; // 6 个字符
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
    }

    /**
     * 测试最大长度验证
     */
    public function testMax(): void
    {
        $data = ['username' => 'verylongusername123'];
        $rules = ['username' => ['max:10']];
        
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
        
        $data = ['username' => 'short'];
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
    }

    /**
     * 测试范围验证
     */
    public function testBetween(): void
    {
        $data = ['age' => 25];
        $rules = ['age' => ['between:18,65']];
        
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
        
        $data = ['age' => 10];
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
    }

    /**
     * 测试正则验证
     */
    public function testRegex(): void
    {
        $data = ['phone' => '13800138000'];
        $rules = ['phone' => ['regex:/^1[3-9]\d{9}$/']];
        
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
        
        $data = ['phone' => '123456'];
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
    }

    /**
     * 测试 in 验证
     */
    public function testIn(): void
    {
        $data = ['status' => 'active'];
        $rules = ['status' => ['in:active,inactive,pending']];
        
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
        
        $data = ['status' => 'invalid'];
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
    }

    /**
     * 测试多个规则
     */
    public function testMultipleRules(): void
    {
        $data = [
            'username' => 'test',
            'email' => 'test@example.com',
            'age' => 25,
        ];
        $rules = [
            'username' => ['required', 'string', 'min:3', 'max:20'],
            'email' => ['required', 'email'],
            'age' => ['required', 'integer', 'min:18'],
        ];
        
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
    }

    /**
     * 测试错误信息获取
     */
    public function testErrors(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => ['required']];
        
        $validator = new Validator($data, $rules);
        $validator->validate();
        
        $errors = $validator->errors();
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('name', $errors);
        
        $firstError = $validator->firstError();
        $this->assertNotNull($firstError);
        
        $fieldErrors = $validator->getFieldErrors('name');
        $this->assertNotEmpty($fieldErrors);
    }

    /**
     * 测试可选字段（非必填时跳过其他规则）
     */
    public function testOptionalField(): void
    {
        $data = [];
        $rules = ['email' => ['email']]; // 没有 required
        
        $validator = new Validator($data, $rules);
        // 字段不存在且不是 required，应该通过验证
        $this->assertTrue($validator->validate());
    }
}
