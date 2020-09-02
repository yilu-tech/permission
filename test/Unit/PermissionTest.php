<?php

namespace Tests\Unit;

use Tests\TestCase;
use YiluTech\Permission\PermissionManager;

class PermissionTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
    }

    public function testSync()
    {
        $data = [
            'server' => 'test',
            'action' => 'sync',
            'data' => resolve(PermissionManager::class)->all()
        ];
        $result = $this->postJson('/permission/sync', $data)->json();
        dd($result);
    }

    public function testGetChanges()
    {
        dd(resolve(PermissionManager::class)->getChanges());
    }

    public function testCreate()
    {
        $data = [
            'name' => 'el.c',
            'type' => 'element'
        ];
        $result = $this->postJson('/permission/create', $data)->json();
        dd($result);
    }

    public function testUpdate()
    {
        $data = [
            'permission_id' => 32,
            'name' => 'el.b',
        ];
        $result = $this->postJson('/permission/update', $data)->json();
        dd($result);
    }

    public function testDelete()
    {
        $data = [
            'name' => 'el.b',
        ];
        $result = $this->postJson('/permission/delete', $data)->json();
        dd($result);
    }

    public function testTranslate()
    {
        $data = [
            'name' => 'el.a',
            'lang' => 'en',
            'content' => 'aa'
        ];
        $result = $this->postJson('/permission/translate', $data)->json();
        dd($result);
    }
}
