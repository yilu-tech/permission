<?php


namespace YiluTech\Permission;


use Illuminate\Support\Str;

class PermissionException extends \Exception
{
    protected $attributes;

    protected $messageKey;

    public function __construct($message = "", $attributes = [])
    {
        $this->messageKey = $message;

        $this->attributes = $attributes;

        parent::__construct($this->makeReplacements($message, $attributes));
    }

    public function getMessageKey()
    {
        return $this->messageKey;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    protected function makeReplacements($line, array $replace)
    {
        if (empty($replace)) {
            return $line;
        }

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . Str::upper($key), ':' . Str::ucfirst($key)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $line
            );
        }

        return $line;
    }
}
