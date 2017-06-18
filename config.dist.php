<?php
return [
    'user' => '',                       // this is used to prevent approving user's PR
    'access_token' => '',               // github personal access token
    'approval_count' => 0,              // determine how many approvals needed before inserting own approval
    'max_retrieval' => 10,              // only process the last 10 updated PRs per repository
    'repositories' => [
        [
            'owner' => '',
            'name' => ''
        ],
        [
            'owner' => '',
            'name' => ''
        ]
    ]
];