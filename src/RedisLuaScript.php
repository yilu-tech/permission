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

    const EXISTS = <<<'LUA'
local exists = redis.call('exists', KEYS[1]..':administrator')
if exists == 1 then
    return 1
end
local key = KEYS[1]
if ARGV[1] ~= nil then
    key = KEYS[1]..':'..ARGV[1]
    exists = redis.call('exists', key..':administrator')
    if exists == 1 then
        return 1
    end
end
return redis.call('hexists', key, KEYS[2])
LUA;

}
