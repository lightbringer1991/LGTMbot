<?php namespace LGTMBot;

require_once 'vendor/autoload.php';

class LGTM
{
    const PATTERN_WIP = '/wip|work in progress/i';
    const PATTERN_DO_NOT_MERGE = '/do not merge|do_not_merge/i';
    const REVIEW_STATUS_APPROVED = 'APPROVED';
    const REVIEW_STATUS_COMMENTED = 'COMMENTED';

    /** @var \Github\Client $client */
    private $client;
    /** @var array $config */
    private $config;
    /** @var \Monolog\Logger $logger */
    private $logger;
    /** @var array $prList */
    private $prList;

    /**
     * LGTM constructor.
     *
     * @param \Github\Client $client
     * @param array $config
     * @param \Monolog\Logger $logger
     */
    public function __construct($client, $config, $logger = null)
    {
        $this->client = $client;
        $this->config = $config;
        if ($logger == null) {
            $logger = new \Monolog\Logger('LGTMbot');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/../activity.log', \Monolog\Logger::DEBUG));
        }
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getPrList()
    {
        return $this->prList;
    }

    /**
     * @return $this
     */
    public function retrievePullRequests()
    {
        $this->prList = [];
        foreach ($this->config['repositories'] as $r) {
            $openPullRequests = $this->client->api('pull_request')->all($r['owner'], $r['name'], [
                'state' => 'open',
                'sort' => 'updated',
                'direction' => 'desc'
            ]);
            $this->prList = array_merge($this->prList, array_slice($openPullRequests, 0, $this->config['max_retrieval']));
        }
        $this->logger->info(sprintf('Retrieved %d pull requests', count($this->prList)));

        return $this;
    }

    /**
     * @return $this
     */
    public function filterWorkInProgressPr()
    {
        $this->prList = array_filter($this->prList, function($pr) {
            if (preg_match(self::PATTERN_WIP, $pr['title']) === 0) {
                return true;
            } else {
                $this->logger->debug(sprintf('Ignore WIP pull request: %d', $pr['number']));
                return false;
            }
        });
        $this->prList = array_values($this->prList);
        return $this;
    }

    /**
     * @return $this
     */
    public function filterDoNotMergePr()
    {
        $this->prList = array_filter($this->prList, function($pr) {
            if (preg_match(self::PATTERN_DO_NOT_MERGE, $pr['title']) === 0) {
                return true;
            } else {
                $this->logger->debug(sprintf('Ignore Do Not Merge pull request: %d', $pr['number']));
                return false;
            }
        });
        $this->prList = array_values($this->prList);
        return $this;
    }

    /**
     * filter PRs that are created by this user
     *
     * @return $this
     */
    public function filterUserPr()
    {
        $this->prList = array_filter($this->prList, function($pr) {
            if ($pr['user']['login'] != $this->config['user']) {
                return true;
            } else {
                $this->logger->debug(sprintf('Ignore user\'s pull request: %d', $pr['number']));
                return false;
            }
        });
        $this->prList = array_values($this->prList);
        return $this;
    }

    /**
     * filter out PRs that have less than X approvals
     * filter out PRs that are already approved by the user
     *
     * @return $this
     */
    public function filterApprovedPr()
    {
        $newList = [];
        foreach ($this->prList as $pr) {
            $owner = $pr['head']['repo']['owner']['login'];
            $name = $pr['head']['repo']['name'];
            $id = $pr['number'];

            $reviews = $this->client->api('pull_request')->reviews()->all($owner, $name, $id);
            $approvalCount = 0;
            $userApproved = false;
            foreach ($reviews as $r) {
                // check if user already approved
                if (($r['user']['login'] == $this->config['user']) && ($r['state'] == self::REVIEW_STATUS_APPROVED)) {
                    $userApproved = true;
                    break;
                }

                if (($r['state'] == self::REVIEW_STATUS_APPROVED) ||
                    (($r['state'] == self::REVIEW_STATUS_COMMENTED) && ($r['body'] == 'LGTM'))) {
                    $approvalCount++;
                }
            }

            if (!$userApproved && ($approvalCount >= $this->config['approval_count'])) {
                $newList[] = $pr;
            } else {
                $this->logger->debug(sprintf('Ignore approved pull requests: %d', $pr['number']));
            }
        }
        $this->prList = $newList;
        return $this;
    }

    /**
     * approve a pull request
     *
     * @param array $pr pull request data
     */
    public function approvePR($pr)
    {
        $owner = $pr['head']['repo']['owner']['login'];
        $name = $pr['head']['repo']['name'];
        $id = $pr['number'];

        $this->client->api('pull_request')->reviews()->create($owner, $name, $id, [
            'event' => 'APPROVE',
            'body' => 'LGTM'
        ]);
    }

    /**
     * approve all pull requests stored in the list
     */
    public function approveAll()
    {
        foreach ($this->prList as $pr) {
            $this->logger->debug(sprintf('Approving %d', $pr['number']));
            $this->approvePR($pr);
        }
    }
}
