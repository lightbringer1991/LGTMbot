# LGTMbot
Simple script to approve pull requests if there are X number of approvals already


## Requirements
- PHP7.0

## Installation
- Install dependencies `composer install`
- Rename `config.dist.php` to `config.php` and fill in the configuration details

## Execution
- Run script `php index.php`
- Log can be viewed in a generated file `activity.log`

## Tests
- Run tests `vendor/bin/phpunit`


## License
Copyright 2017 [Tuan Nguyen]

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License. You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
