<?php

namespace Framework\Core;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * 依赖注入容器
 * 实现 PSR-11 ContainerInterface
 * 支持单例、工厂模式、自动解析依赖
 */
class Container implements ContainerInterface
{
    /**
     * 容器实例（单例模式）
     */
    private static ?Container $instance = null;

    /**
     * 绑定映射表
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * 单例实例缓存
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * 单例标记
     * @var array<string, bool>
     */
    private array $singletons = [];

    /**
     * 懒加载标记
     * @var array<string, bool>
     */
    private array $lazyBindings = [];

    /**
     * 获取容器单例
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 重置容器单例（用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 绑定服务（每次解析都创建新实例）
     * 
     * @param string $abstract 抽象名称（接口或类名）
     * @param callable|string|null $concrete 具体实现（闭包、类名或 null 表示绑定自身）
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = $concrete;
        unset($this->singletons[$abstract]);
    }

    /**
     * 单例绑定（只创建一次实例）
     * 
     * @param string $abstract 抽象名称
     * @param callable|string|null $concrete 具体实现
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    /**
     * 懒加载绑定（延迟实例化，只有在实际使用时才创建）
     * 
     * @param string $abstract 抽象名称
     * @param callable|string|null $concrete 具体实现
     */
    public function lazy(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
        $this->lazyBindings[$abstract] = true;
    }

    /**
     * 直接注册一个已存在的实例
     * 
     * @param string $abstract 抽象名称
     * @param object $instance 实例对象
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->singletons[$abstract] = true;
    }

    /**
     * PSR-11: 获取服务
     * 
     * @param string $id 服务标识
     * @return mixed 解析后的实例
     * @throws ContainerException 解析失败时抛出
     * @throws NotFoundException 服务未找到时抛出
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (\Exception $e) {
            if (!$this->has($id)) {
                throw new NotFoundException("服务未找到: {$id}");
            }
            throw new ContainerException("解析服务失败: {$id}", 0, $e);
        }
    }

    /**
     * PSR-11: 检查服务是否存在
     * 
     * @param string $id 服务标识
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) 
            || isset($this->instances[$id]) 
            || class_exists($id);
    }

    /**
     * 解析服务
     * 
     * @param string $abstract 抽象名称
     * @param array $parameters 构造参数（可选）
     * @return mixed 解析后的实例
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        // 如果已有实例缓存，直接返回
        if (isset($this->instances[$abstract])) {
            $instance = $this->instances[$abstract];
            // 如果是懒加载代理，返回实际实例
            if ($instance instanceof LazyProxy) {
                return $instance->getInstance();
            }
            return $instance;
        }

        // 获取绑定
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // 检查是否需要懒加载
        if (isset($this->lazyBindings[$abstract]) && empty($parameters)) {
            $proxy = new LazyProxy($abstract, function () use ($abstract, $concrete) {
                return $this->buildInstance($concrete, []);
            });
            $this->instances[$abstract] = $proxy;
            return $proxy;
        }

        // 构建实例
        $object = $this->buildInstance($concrete, $parameters);

        // 如果是单例，缓存实例
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 构建实例
     */
    private function buildInstance(mixed $concrete, array $parameters): object
    {
        // 如果是闭包，直接执行
        if (is_callable($concrete) && !is_string($concrete)) {
            return $concrete($this, $parameters);
        }

        // 字符串类名，使用反射创建
        return $this->build($concrete, $parameters);
    }

    /**
     * 使用反射构建对象
     * 
     * @param string $concrete 类名
     * @param array $parameters 构造参数
     * @return object 实例
     */
    private function build(string $concrete, array $parameters = []): object
    {
        if (!class_exists($concrete)) {
            throw new ContainerException("类不存在: {$concrete}");
        }

        $reflector = new ReflectionClass($concrete);

        // 检查是否可实例化
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("类 {$concrete} 无法实例化");
        }

        // 获取构造函数
        $constructor = $reflector->getConstructor();

        // 无构造函数，直接创建
        if ($constructor === null) {
            return new $concrete();
        }

        // 解析构造函数参数
        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * 解析依赖参数
     * 
     * @param ReflectionParameter[] $parameters 参数列表
     * @param array $provided 提供的参数
     * @return array 解析后的参数值
     */
    private function resolveDependencies(array $parameters, array $provided = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // 如果提供了该参数，直接使用
            if (isset($provided[$name])) {
                $dependencies[] = $provided[$name];
                continue;
            }

            // 如果有类型提示，尝试从容器解析
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                try {
                    $dependencies[] = $this->make($typeName);
                } catch (\Exception $e) {
                    // 如果参数有默认值，使用默认值
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new ContainerException("无法解析依赖: {$name}");
                    }
                }
            } else {
                // 基本类型或没有类型提示，使用默认值或抛出异常
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException("无法解析依赖: {$name}");
                }
            }
        }

        return $dependencies;
    }

    /**
     * 获取已绑定的服务列表（调试用）
     */
    public function getBindings(): array
    {
        return array_keys($this->bindings);
    }

    /**
     * 移除绑定
     */
    public function forget(string $abstract): void
    {
        unset(
            $this->bindings[$abstract], 
            $this->instances[$abstract], 
            $this->singletons[$abstract],
            $this->lazyBindings[$abstract]
        );
    }

    /**
     * 检查服务是否已初始化（用于懒加载检测）
     */
    public function isInitialized(string $abstract): bool
    {
        if (!isset($this->instances[$abstract])) {
            return false;
        }
        
        $instance = $this->instances[$abstract];
        if ($instance instanceof LazyProxy) {
            return $instance->isInitialized();
        }
        
        return true;
    }

    /**
     * 获取懒加载统计
     */
    public function getLazyStats(): array
    {
        $stats = [
            'total_lazy' => count($this->lazyBindings),
            'initialized' => 0,
            'pending' => 0,
        ];

        foreach ($this->lazyBindings as $abstract => $isLazy) {
            if ($this->isInitialized($abstract)) {
                $stats['initialized']++;
            } else {
                $stats['pending']++;
            }
        }

        return $stats;
    }
}

/**
 * PSR-11 容器异常
 */
class ContainerException extends \Exception implements \Psr\Container\ContainerExceptionInterface
{
}

/**
 * PSR-11 未找到异常
 */
class NotFoundException extends \Exception implements \Psr\Container\NotFoundExceptionInterface
{
}
