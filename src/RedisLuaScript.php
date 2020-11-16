<?php


namespace YiluTech\Permission;

class RedisLuaScript
{
    const EXISTS = <<<'LUA'
KEYS[2] = KEYS[1]..':administrator'
if redis.call('exists', KEYS[2]) == 1 then
    return 1
end
if ARGV[1] == nil then
    return 0
end
if ARGV[2] == nil then
    return redis.call('hexists', KEYS[1], ARGV[1])
end
KEYS[3] = KEYS[1]..':'..ARGV[1]
KEYS[4] = KEYS[3]..':administrator'
if redis.call('exists', KEYS[4]) == 1 then
    return 1
end
return redis.call('hexists', KEYS[3], ARGV[2])
LUA;

}
