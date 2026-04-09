<?php

namespace App\Controllers;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Validation\Validator;
use App\Models\User;

/**
 * 用户控制器
 * 提供用户 CRUD API
 */
class UserController
{
    /**
     * 获取用户列表（分页）
     */
    public function index(Request $request): Response
    {
        try {
            $pageSize = (int) $request->input('page_size', 15);
            $pageSize = min(max($pageSize, 1), 100);

            $paginator = User::paginate($pageSize);

            return Response::success([
                'items' => array_map(fn($user) => $user->toArray(), $paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                    'has_previous' => $paginator->hasPreviousPage(),
                    'has_next' => $paginator->hasNextPage(),
                ],
            ], '获取成功');
        } catch (\Throwable $e) {
            return Response::error('获取用户列表失败，请稍后重试', 500, null, 500);
        }
    }

    /**
     * 获取单个用户
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return Response::error('用户不存在', 404, null, 404);
            }

            return Response::success($user->toArray(), '获取成功');
        } catch (\Throwable $e) {
            return Response::error('获取用户信息失败，请稍后重试', 500, null, 500);
        }
    }

    /**
     * 创建用户
     */
    public function store(Request $request): Response
    {
        $data = $request->all();

        // 验证数据
        $validator = new Validator($data, [
            'username' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (!$validator->validate()) {
            return Response::error('验证失败', 422, $validator->errors(), 422);
        }

        try {
            // 创建用户
            $user = new User();
            $user->fill([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);

            if ($user->save()) {
                return Response::success($user->toArray(), '创建成功')->setStatusCode(201);
            }

            return Response::error('创建失败，请稍后重试', 500, null, 500);
        } catch (\Throwable $e) {
            // 检查是否为唯一约束冲突（重复用户名/邮箱）
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                return Response::error('用户名或邮箱已存在', 409, null, 409);
            }
            return Response::error('创建用户失败，请稍后重试', 500, null, 500);
        }
    }

    /**
     * 更新用户
     */
    public function update(Request $request, int $id): Response
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return Response::error('用户不存在', 404, null, 404);
            }

            $data = $request->all();

            // 验证数据
            $rules = [];
            if (isset($data['username'])) {
                $rules['username'] = ['string', 'min:2', 'max:50'];
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
                    return Response::error('验证失败', 422, $validator->errors(), 422);
                }
            }

            // 更新用户
            if (isset($data['username'])) {
                $user->username = $data['username'];
            }
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }
            if (isset($data['password'])) {
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if ($user->save()) {
                return Response::success($user->toArray(), '更新成功');
            }

            return Response::error('更新失败，请稍后重试', 500, null, 500);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                return Response::error('用户名或邮箱已存在', 409, null, 409);
            }
            return Response::error('更新用户失败，请稍后重试', 500, null, 500);
        }
    }

    /**
     * 删除用户
     */
    public function destroy(Request $request, int $id): Response
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return Response::error('用户不存在', 404, null, 404);
            }

            if ($user->delete()) {
                return Response::success(null, '删除成功');
            }

            return Response::error('删除失败，请稍后重试', 500, null, 500);
        } catch (\Throwable $e) {
            return Response::error('删除用户失败，请稍后重试', 500, null, 500);
        }
    }
}
