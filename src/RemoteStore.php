<?php


namespace YiluTech\Permission;


use GuzzleHttp\Client;

class RemoteStore extends LocalStore
{
    protected $url;

    public function __construct($service, $options)
    {
        parent::__construct($service, $options);
        $this->url = $this->option('url') . '/permission/call';
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

    public function rollback($steps = 1)
    {
        return $this->request('rollback', compact('steps'));
    }

    public function mergeTo($file)
    {
        return $this->request('mergeTo', compact('file'));
    }

    public function getMigrated()
    {
        return $this->request('getMigrated', ['steps' => -1]);
    }

    public function test()
    {
        $migrations = $this->getUndoMigrations();
        if (empty($migrations)) {
            return [[], []];
        }
        $multipart = [];
        foreach ($migrations as $name => $path) {
            $multipart[] = ['name' => 'migrations[]', 'filename' => $name, 'contents' => fopen($path, 'r')];
        }
        return [array_keys($migrations), $this->request('getChanges', [], compact('multipart'))];
    }

    public function items()
    {
        return $this->request('getItems');
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
