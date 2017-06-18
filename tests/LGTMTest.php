<?php namespace LGTMBot\Test;

require_once __DIR__ . '/../vendor/autoload.php';

use Github\Client;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use LGTMBot\LGTM;

class LGTMTest extends TestCase
{
    /** @var Client $client */
    private $client;
    /** @var array $config */
    private $config;
    /** @var Logger $logger */
    private $logger;

    /**
     * @param mixed $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @param mixed $object
     * @param string $propertyName
     * @return mixed
     * @throws \Exception
     */
    public function getProperty($object, $propertyName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        while ($reflection) {
            try {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                return $property->getValue($object);
            } catch (\ReflectionException $e) {
                $reflection = $reflection->getParentClass();
            }
        }
        throw new \Exception("No property $propertyName found");
    }

    /**
     * @param mixed $object
     * @param string $propertyName
     * @param mixed $value
     */
    public function setProperty(&$object, $propertyName, $value)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $reflection_property = $reflection->getProperty($propertyName);
        $reflection_property->setAccessible(true);

        $reflection_property->setValue($object, $value);
    }

    /**
     * @before
     */
    public function beforeEach()
    {
        $this->client = new MockClient();
        $this->logger = new MockLogger();

        $this->config = [
            'user' => 'user1',
            'access_token' => 'fake_token',
            'approval_count' => 0,
            'max_retrieval' => 10,
            'repositories' => [
                [
                    'owner' => 'user2',
                    'name' => 'test_repo_1'
                ],
                [
                    'owner' => 'user3',
                    'name' => 'test_repo_2'
                ]
            ]
        ];
    }

    /**
     * @testdox test class constructor
     */
    public function testConstructor()
    {
        // test if logger object is initialised
        $lgtm = new LGTM($this->client, $this->config);
        $this->assertEquals(Logger::class, get_class($this->getProperty($lgtm, 'logger')));
    }

    /**
     * @testdox test retrievePullRequests() function
     */
    public function testRetrievePullRequests()
    {
        // test if the function returns all open pull requests
        $this->client->mockData['pull_request'] = [
            'user2' => [
                'test_repo_1' => [
                    [
                        'id' => 1,
                    ],
                    [
                        'id' => 2,
                    ]
                ]
            ],
            'user3' => [
                'test_repo_2' => [
                    [
                        'id' => 3,
                    ]
                ]
            ]
        ];

        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $lgtmObj->retrievePullRequests();
        $this->assertEquals([
            [
                'id' => 1,
            ],
            [
                'id' => 2,
            ],
            [
                'id' => 3,
            ]
        ], $lgtmObj->getPrList());

        // test if the function restricts the returned number of pull requests
        $this->config['max_retrieval'] = 1;
        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $lgtmObj->retrievePullRequests();
        $this->assertEquals([
            [
                'id' => 1,
            ],
            [
                'id' => 3,
            ]
        ], $lgtmObj->getPrList());
    }

    /**
     * @testdox test filterWorkInProgressPr() function
     */
    public function testFilterWorkInProgressPr()
    {
        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $this->setProperty($lgtmObj, 'prList', [
            ['number' => 1, 'title' => '[WIP] test 1'],
            ['number' => 2, 'title' => 'wip test 2'],
            ['number' => 3, 'title' => '[wip] test 3'],
            ['number' => 4, 'title' => 'test 4 (WIP)'],
            ['number' => 5, 'title' => 'test 5 wip'],
            ['number' => 6, 'title' => 'test 6']
        ]);
        $lgtmObj->filterWorkInProgressPr();
        $this->assertEquals([
            ['number' => 6, 'title' => 'test 6']
        ], $lgtmObj->getPrList());
    }

    /**
     * @testdox test filterDoNotMergePr() function
     */
    public function testFilterDoNotMergePr()
    {
        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $this->setProperty($lgtmObj, 'prList', [
            ['number' => 1, 'title' => 'do not merge test 1'],
            ['number' => 2, 'title' => '[do not merge] test 2'],
            ['number' => 3, 'title' => '[DO NOT MERGE] test 3'],
            ['number' => 4, 'title' => 'test 4 (do_not_merge)'],
            ['number' => 5, 'title' => 'DO_NOT_MERGE test 5'],
            ['number' => 6, 'title' => 'test 6']
        ]);
        $lgtmObj->filterDoNotMergePr();
        $this->assertEquals([
            ['number' => 6, 'title' => 'test 6']
        ], $lgtmObj->getPrList());
    }

    /**
     * @testdox test filterUserPr() function
     */
    public function testFilterUserPr()
    {
        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $this->setProperty($lgtmObj, 'prList', [
            ['number' => 1, 'user' => ['login' => 'user1']],
            ['number' => 2, 'user' => ['login' => 'user2']],
            ['number' => 3, 'user' => ['login' => 'user3']],
        ]);
        $lgtmObj->filterUserPr();
        $this->assertEquals([
            ['number' => 2, 'user' => ['login' => 'user2']],
            ['number' => 3, 'user' => ['login' => 'user3']],
        ], $lgtmObj->getPrList());
    }

    /**
     * @testdox test filterApprovedPr() function
     */
    public function testFilterApprovedPr()
    {
        $this->client->mockData['reviews'] = [
            'user1' => [
                'repo1' => [
                    // this PR is already approved by user
                    1 => [
                        [
                            'user' => [
                                'login' => 'user1'
                            ],
                            'state' => LGTM::REVIEW_STATUS_APPROVED
                        ]
                    ],
                    // this is just a comment
                    2 => [
                        [
                            'user' => [
                                'login' => 'user2'
                            ],
                            'state' => LGTM::REVIEW_STATUS_COMMENTED,
                            'body' => 'random comment'
                        ],
                        [
                            'user' => [
                                'login' => 'user2'
                            ],
                            'state' => LGTM::REVIEW_STATUS_APPROVED,
                            'body' => 'LGTM'
                        ]
                    ],
                    3 => [
                        [
                            'user' => [
                                'login' => 'user3'
                            ],
                            'state' => LGTM::REVIEW_STATUS_COMMENTED,
                            'body' => 'LGTM'
                        ]
                    ]
                ]
            ]
        ];
        $this->config['approval_count'] = 1;

        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $this->setProperty($lgtmObj, 'prList', [
            [
                'number' => 1,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user1'
                        ],
                        'name' => 'repo1'
                    ]
                ]
            ],
            [
                'number' => 2,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user1'
                        ],
                        'name' => 'repo1'
                    ]
                ]
            ],
            [
                'number' => 3,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user1'
                        ],
                        'name' => 'repo1'
                    ]
                ]
            ],
        ]);
        $lgtmObj->filterApprovedPr();
        $this->assertEquals([
            [
                'number' => 2,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user1'
                        ],
                        'name' => 'repo1'
                    ]
                ]
            ],
            [
                'number' => 3,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user1'
                        ],
                        'name' => 'repo1'
                    ]
                ]
            ]
        ], $lgtmObj->getPrList());
    }

    /**
     * @testdox test approvePR() function
     */
    public function testApprovePR()
    {
        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $lgtmObj->approvePR([
            'number' => 2,
            'head' => [
                'repo' => [
                    'owner' => [
                        'login' => 'user1'
                    ],
                    'name' => 'repo1'
                ]
            ]
        ]);

        $this->assertEquals([
            [
                'owner' => 'user1',
                'name' => 'repo1',
                'id' => 2,
                'data' => [
                    'event' => 'APPROVE',
                    'body' => 'LGTM'
                ]
            ]
        ], $this->client->functionCallParams);
    }

    /**
     * @testdox test approveAll() function
     */
    public function testApproveAll()
    {
        $lgtmObj = new LGTM($this->client, $this->config, $this->logger);
        $this->setProperty($lgtmObj, 'prList', [
            [
                'number' => 2,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user2'
                        ],
                        'name' => 'repo2'
                    ]
                ]
            ],
            [
                'number' => 3,
                'head' => [
                    'repo' => [
                        'owner' => [
                            'login' => 'user1'
                        ],
                        'name' => 'repo1'
                    ]
                ]
            ],
        ]);

        $lgtmObj->approveAll();
        $this->assertEquals([
            [
                'owner' => 'user2',
                'name' => 'repo2',
                'id' => 2,
                'data' => [
                    'event' => 'APPROVE',
                    'body' => 'LGTM'
                ]
            ],
            [
                'owner' => 'user1',
                'name' => 'repo1',
                'id' => 3,
                'data' => [
                    'event' => 'APPROVE',
                    'body' => 'LGTM'
                ]
            ]
        ], $this->client->functionCallParams);
    }
}
