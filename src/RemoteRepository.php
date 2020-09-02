<?php


namespace YiluTech\Permission;


use GuzzleHttp\Client;

class RemoteRepository
{
    protected $url;

    protected $server;

    public function __construct($server, $url)
    {
        $this->url = $url . '/permission/sync';
        $this->server = $server;
    }

    public function sync($data)
    {
        return $this->call('sync', compact('data'));
    }

    public function getChanges($data)
    {
        return $this->call('getChanges', compact('data'));
    }

    protected function call($cation, $options = [])
    {
        $options['action'] = $cation;
        $options['server'] = $this->server;
        $content = (new Client)->post($this->url, ['json' => $options])->getBody()->getContents();
        return json_decode($content, true);
    }
}
