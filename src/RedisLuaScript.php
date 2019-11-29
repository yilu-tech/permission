<?php


namespace YiluTech\Permission;

class RedisLuaScript
{
    const DEL = <<<'LUA'
local keys = redis.call('smembers', KEYS[1])
if table.getn(keys) > 0 then
    redis.call('del', unpack(keys))
end
return 1
LUA;
}
