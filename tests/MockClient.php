<?php namespace LGTMBot\Test;

class MockClient
{
    public $mockData = [];
    public $currentApi;
    public $functionCallParams;

    public function __construct()
    {
        $this->mockData = [
            // structure: [$owner => [$name => [array of data]], ...]
            'pull_request' => [],
            // structure: [$owner => [$name => [$id => [array of data]]], ...]
            'reviews' => []
        ];
    }

    public function api($apiName)
    {
        $this->currentApi = $apiName;
        return $this;
    }

    public function all($owner, $name, $data)
    {
        $this->functionCallParams[] = [
            'owner' => $owner,
            'name' => $name,
            'data' => $data
        ];
        switch ($this->currentApi) {
            case 'pull_request':
                return $this->mockData[$this->currentApi][$owner][$name];
            case 'reviews':
                // hacky solution, data should not be the id
                return $this->mockData[$this->currentApi][$owner][$name][$data];
            default:
                return [];
        }
    }

    public function reviews()
    {
        if ($this->currentApi !== 'pull_request') {
            throw new \Exception('Invalid function `reviews` call');
        }
        $this->currentApi = 'reviews';
        return $this;
    }

    public function create($owner, $name, $id, $data)
    {
        if ($this->currentApi !== 'reviews') {
            throw new \Exception('Invalid function `create` call');
        }
        $this->functionCallParams[] = [
            'owner' => $owner,
            'name' => $name,
            'id' => $id,
            'data' => $data
        ];
    }
}