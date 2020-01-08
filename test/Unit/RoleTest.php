<?php

namespace Tests\Unit;

use Tests\TestCase;

class RoleTest extends TestCase
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

    public function testCreateRole()
    {
        $data = [
            'name' => 'parent1',
            'status' => [RS_EXTEND, RS_WRITE],
            'roles' => [],
            'permissions' => [33,34]
        ];
        $result = $this->postJson('/role/create', $data)->json();
        dd($result);
    }

    public function testUpdateRole()
    {
        $data = [
            'role_id' => 4,
            'name' => '测试-child',
            'roles' => [2]
        ];
        $result = $this->postJson('/role/update', $data)->json();
        dd($result);
    }

    public function testDeleteRole()
    {
        $data = [
            'role_id' => 4,
        ];
        $result = $this->postJson('/role/delete', $data)->json();
        dd($result);
    }
}
