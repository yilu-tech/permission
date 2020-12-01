<?php


namespace YiluTech\Permission;


use GuzzleHttp\Client;
use Illuminate\Support\Arr;

class RemoteStore extends LocalStore
{
    protected $url;

    public function __construct($service, $name, $url)
    {
        parent::__construct($service, $name);
        $this->url = $url . '/permission/call';
    }

    public function migrate()
    {
        return array_keys(tap($this->getUndoMigrations(), function ($migrations) {
            if (empty($migrations)) {
                return;
            }
            $multipart = [];
            foreach ($migrations as $name => $path) {
                $multipart[] = ['name' => 'migrations[]', 'filename' => $name, 'contents' => fopen($path, 'r')];
            }
            $this->request('migrate', [], compact('multipart'));
        }));
    }

    public function rollback()
    {
        return $this->request('rollback');
    }

    public function items()
    {
        return $this->request('getItems');
    }

    public function getMigrated()
    {
        return Arr::pluck($this->request('getMigrated', ['times' => -1]), 'migration');
    }

    protected function request($action, $data = [], $options = [])
    {
        $client = new Client();
        $options['query']['action'] = $action;
        $options['query']['service'] = $this->service;
        $options['headers']['Accept'] = 'application/json';

        if (!empty($data)) {
            $options['json'] = $data;
        }

        $response = $client->post($this->url, $options);
        $result = json_decode($response->getBody()->getContents(), JSON_OBJECT_AS_ARRAY);
        if ($result['code'] === 'success') {
            return $result['data'];
        }
        throw new PermissionException($result['message']);
    }
}
