<?php


namespace YiluTech\Permission;



class PermissionException extends \Exception
{
    protected $attributes;

    public function __construct($message = "", $attributes = [])
    {
        $this->attributes = $attributes;

        parent::__construct($message);
    }

    public function getAttributes()
    {
        return $this->attributes;
    }
}
