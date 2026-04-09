<?php

namespace Framework\Database;

/**
 * 分页器
 * 封装分页数据和分页链接生成
 */
class Paginator
{
    /**
     * 数据列表
     * @var array<array<string, mixed>>
     */
    private array $items;

    /**
     * 当前页码
     */
    private int $currentPage;

    /**
     * 每页数量
     */
    private int $perPage;

    /**
     * 总记录数
     */
    private int $total;

    /**
     * 总页数
     */
    private int $totalPages;

    /**
     * 是否有上一页
     */
    private bool $hasPrevious;

    /**
     * 是否有下一页
     */
    private bool $hasNext;

    public function __construct(array $items, int $total, int $currentPage, int $perPage)
    {
        $this->items = $items;
        $this->total = $total;
        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->totalPages = (int) ceil($total / $perPage);
        $this->hasPrevious = $currentPage > 1;
        $this->hasNext = $currentPage < $this->totalPages;
    }

    /**
     * 获取数据列表
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * 获取数据列表（Laravel 风格别名）
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * 获取当前页码
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 获取当前页码（Laravel 风格别名）
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 获取每页数量
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * 获取每页数量（Laravel 风格别名）
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * 获取总记录数
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * 获取总记录数（Laravel 风格别名）
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * 获取总页数
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * 获取总页数（Laravel 风格别名）
     */
    public function lastPage(): int
    {
        return $this->totalPages;
    }

    /**
     * 是否有上一页
     */
    public function hasPrevious(): bool
    {
        return $this->hasPrevious;
    }

    /**
     * 是否有上一页（Laravel 风格别名）
     */
    public function hasPreviousPage(): bool
    {
        return $this->hasPrevious;
    }

    /**
     * 是否有下一页
     */
    public function hasNext(): bool
    {
        return $this->hasNext;
    }

    /**
     * 是否有下一页（Laravel 风格别名）
     */
    public function hasNextPage(): bool
    {
        return $this->hasNext;
    }

    /**
     * 获取上一页页码
     */
    public function getPreviousPage(): ?int
    {
        return $this->hasPrevious ? $this->currentPage - 1 : null;
    }

    /**
     * 获取下一页页码
     */
    public function getNextPage(): ?int
    {
        return $this->hasNext ? $this->currentPage + 1 : null;
    }

    /**
     * 转换为数组（用于 JSON 响应）
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'pagination' => [
                'current_page' => $this->currentPage,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'total_pages' => $this->totalPages,
                'has_previous' => $this->hasPrevious,
                'has_next' => $this->hasNext,
                'previous_page' => $this->getPreviousPage(),
                'next_page' => $this->getNextPage(),
            ],
        ];
    }
}
