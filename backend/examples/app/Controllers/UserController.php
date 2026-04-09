<?php

namespace App\Controllers;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Validation\Validator;
use App\Models\User;

/**
 * 用户控制器
 */
class UserController
{
    /**
     * 获取用户列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('page_size', 15);

        $paginator = User::paginate($pageSize, $page);

        return Response::success($paginator->toArray(), '获取成功');
    }

    /**
     * 获取单个用户
     */
    public function show(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if (!$user) {
            return Response::error('用户不存在', 404);
        }

        return Response::success($user->toArray(), '获取成功');
    }

    /**
     * 创建用户
     */
    public function store(Request $request): Response
    {
        $data = $request->all();

        // 验证数据
        $validator = new Validator($data, [
            'username' => ['required', 'string', 'min:3', 'max:20'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (!$validator->validate()) {
            return Response::error('验证失败', 422, $validator->errors());
        }

        // 检查用户名是否已存在
        $exists = User::where('username', $data['username'])->first();
        if ($exists) {
            return Response::error('用户名已存在', 400);
        }

        // 检查邮箱是否已存在
        $exists = User::where('email', $data['email'])->first();
        if ($exists) {
            return Response::error('邮箱已存在', 400);
        }

        // 创建用户
        $user = new User();
        $user->fill([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        if ($user->save()) {
            return Response::success($user->toArray(), '创建成功', 201);
        }

        return Response::error('创建失败', 500);
    }

    /**
     * 更新用户
     */
    public function update(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if (!$user) {
            return Response::error('用户不存在', 404);
        }

        $data = $request->all();

        // 验证数据
        $rules = [];
        if (isset($data['username'])) {
            $rules['username'] = ['string', 'min:3', 'max:20'];
        }
        if (isset($data['email'])) {
            $rules['email'] = ['email'];
        }
        if (isset($data['password'])) {
            $rules['password'] = ['string', 'min:6'];
        }

        if (!empty($rules)) {
            $validator = new Validator($data, $rules);
            if (!$validator->validate()) {
                return Response::error('验证失败', 422, $validator->errors());
            }
        }

        // 更新用户
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $user->fill($data);

        if ($user->save()) {
            return Response::success($user->toArray(), '更新成功');
        }

        return Response::error('更新失败', 500);
    }

    /**
     * 删除用户
     */
    public function destroy(Request $request, string $id): Response
    {
        $user = User::find((int) $id);

        if (!$user) {
            return Response::error('用户不存在', 404);
        }

        if ($user->delete()) {
            return Response::success(null, '删除成功');
        }

        return Response::error('删除失败', 500);
    }
}
