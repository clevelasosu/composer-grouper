# OSUCOE Grouper Client
## Installation

```bash
composer config repositories.engrcommon vcs https://nemo.engr.oregonstate.edu/repos/composer/engrcommon
composer config repositories.grouper vcs https://github.com/clevelasosu/composer-grouper

```
## Usage

```php
use OSUCOE\Grouper\Grouper;

$grouper = new Grouper(GROUPER_BASEURL, GROUPER_LOGIN, GROUPER_PASS);

print_r($grouper->getMembers(GROUPER_GROUP));
$grouper->addUsersToGroup($users, GROUPER_GROUP);
```