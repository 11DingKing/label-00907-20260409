<?php

namespace Tests\Unit\Storage;

use Framework\Storage\Storage;
use Framework\Storage\LocalStorage;
use PHPUnit\Framework\TestCase;

/**
 * Storage（文件存储）测试用例
 */
class StorageTest extends TestCase
{
    private LocalStorage $storage;
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/storage_test';
        $this->storage = new LocalStorage($this->storagePath);
    }

    protected function tearDown(): void
    {
        $this->storage->deleteDirectory('');
    }

    /**
     * 测试写入和读取文件
     */
    public function testPutAndGet(): void
    {
        $this->storage->put('test.txt', 'Hello World');

        $content = $this->storage->get('test.txt');
        $this->assertEquals('Hello World', $content);
    }

    /**
     * 测试文件是否存在
     */
    public function testExists(): void
    {
        $this->assertFalse($this->storage->exists('nonexistent.txt'));

        $this->storage->put('exists.txt', 'content');
        $this->assertTrue($this->storage->exists('exists.txt'));
    }

    /**
     * 测试删除文件
     */
    public function testDelete(): void
    {
        $this->storage->put('to_delete.txt', 'content');
        $this->assertTrue($this->storage->exists('to_delete.txt'));

        $this->storage->delete('to_delete.txt');
        $this->assertFalse($this->storage->exists('to_delete.txt'));
    }

    /**
     * 测试复制文件
     */
    public function testCopy(): void
    {
        $this->storage->put('original.txt', 'original content');

        $this->storage->copy('original.txt', 'copied.txt');

        $this->assertTrue($this->storage->exists('original.txt'));
        $this->assertTrue($this->storage->exists('copied.txt'));
        $this->assertEquals('original content', $this->storage->get('copied.txt'));
    }

    /**
     * 测试移动文件
     */
    public function testMove(): void
    {
        $this->storage->put('source.txt', 'content');

        $this->storage->move('source.txt', 'destination.txt');

        $this->assertFalse($this->storage->exists('source.txt'));
        $this->assertTrue($this->storage->exists('destination.txt'));
        $this->assertEquals('content', $this->storage->get('destination.txt'));
    }

    /**
     * 测试获取文件大小
     */
    public function testSize(): void
    {
        $content = 'Hello World';
        $this->storage->put('size.txt', $content);

        $size = $this->storage->size('size.txt');
        $this->assertEquals(strlen($content), $size);
    }

    /**
     * 测试获取最后修改时间
     */
    public function testLastModified(): void
    {
        $this->storage->put('modified.txt', 'content');

        $lastModified = $this->storage->lastModified('modified.txt');
        $this->assertGreaterThan(0, $lastModified);
        $this->assertLessThanOrEqual(time(), $lastModified);
    }

    /**
     * 测试获取 MIME 类型
     */
    public function testMimeType(): void
    {
        $this->storage->put('test.txt', 'plain text content');

        $mimeType = $this->storage->mimeType('test.txt');
        $this->assertEquals('text/plain', $mimeType);
    }

    /**
     * 测试列出目录文件
     */
    public function testFiles(): void
    {
        $this->storage->put('file1.txt', 'content1');
        $this->storage->put('file2.txt', 'content2');
        $this->storage->makeDirectory('subdir');

        $files = $this->storage->files('');

        $this->assertContains('file1.txt', $files);
        $this->assertContains('file2.txt', $files);
        $this->assertNotContains('subdir', $files);
    }

    /**
     * 测试列出目录
     */
    public function testDirectories(): void
    {
        $this->storage->makeDirectory('dir1');
        $this->storage->makeDirectory('dir2');
        $this->storage->put('file.txt', 'content');

        $directories = $this->storage->directories('');

        $this->assertContains('dir1', $directories);
        $this->assertContains('dir2', $directories);
        $this->assertNotContains('file.txt', $directories);
    }

    /**
     * 测试创建目录
     */
    public function testMakeDirectory(): void
    {
        $this->storage->makeDirectory('new_dir/sub_dir');

        $this->assertTrue(is_dir($this->storagePath . '/new_dir/sub_dir'));
    }

    /**
     * 测试删除目录
     */
    public function testDeleteDirectory(): void
    {
        $this->storage->makeDirectory('to_delete');
        $this->storage->put('to_delete/file.txt', 'content');

        $this->storage->deleteDirectory('to_delete');

        $this->assertFalse(is_dir($this->storagePath . '/to_delete'));
    }

    /**
     * 测试获取文件 URL
     */
    public function testUrl(): void
    {
        $url = $this->storage->url('images/photo.jpg');

        $this->assertEquals('/storage/images/photo.jpg', $url);
    }

    /**
     * 测试追加内容
     */
    public function testAppend(): void
    {
        $this->storage->put('append.txt', 'Hello');
        $this->storage->append('append.txt', ' World');

        $content = $this->storage->get('append.txt');
        $this->assertEquals('Hello World', $content);
    }

    /**
     * 测试在开头添加内容
     */
    public function testPrepend(): void
    {
        $this->storage->put('prepend.txt', 'World');
        $this->storage->prepend('prepend.txt', 'Hello ');

        $content = $this->storage->get('prepend.txt');
        $this->assertEquals('Hello World', $content);
    }

    /**
     * 测试嵌套目录写入
     */
    public function testNestedDirectoryWrite(): void
    {
        $this->storage->put('deep/nested/path/file.txt', 'content');

        $this->assertTrue($this->storage->exists('deep/nested/path/file.txt'));
        $this->assertEquals('content', $this->storage->get('deep/nested/path/file.txt'));
    }
}
