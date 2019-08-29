# Enflow's defaults for Laravel

The `component-laravel` package provides a sane-default configuration for Enflow based applications. The LaravelServiceProvider registers some classes, middleware, and enables Skyline cluster support.

## Installation
You can install the package via composer:

``` bash
composer require enflow/component-laravel
```

## Configuration
This package registers automatically trough Laravel's autoloading.
 
## Exception handling
 To include this ExceptionHandler to enable prettier error handling, extend the `\Enflow\Component\Laravel\AbstractExceptionHandler` from `app/Exceptions/Handler.php` file like this:

```
<?php

namespace App\Exceptions;

use Enflow\Component\Laravel\AbstractExceptionHandler;

class Handler extends AbstractExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];
}
```

To enable Bugsnag:
1. Add a new Laravel project to your Bugsnag account and enable Slack notitifications to #failure
2. Add Bugsnag to your services.php file:
```
    'bugsnag' => [
        'api_key' => env('BUGSNAG_API_KEY'),
    ],
```
3. Add the BUGSNAG_API_KEY to your .env file

## Commands

### db:sync

db:sync enabled you to mysqldump a production database to your local machine and import it automatically. 

#### Performance
To improve import time of db:sync script (tested with a 2GB database) takes ~60 min, just to import.

1. Ensure you are running MySQL 8.0 or higher on your Linux machine directly (legacy systems that connect from WSL to Windows based MySQL are much slower).
2. Change configuration settings to improve buffer sizes to allow faster importing.

`sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf`

Add the following after the `[mysqld]` block:

```
innodb_buffer_pool_size=4G
innodb_log_buffer_size=256M
innodb_log_file_size=1G
innodb_write_io_threads=16
innodb_flush_log_at_trx_commit=0
max_allowed_packet=1024M
read_buffer_size=2M
```

Restart MySQL to let these variables take effect: `sudo service mysql restart`

Ensure the variables are applied by checking it directly via `mysql`:
`SHOW VARIABLES LIKE "innodb_buffer_pool_size";`

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security
If you discover any security related issues, please email michel@enflow.nl instead of using the issue tracker.

## Credits
- [Michel Bardelmeijer](https://github.com/mbardelmeijer)
- [All Contributors](../../contributors)

## About Enflow
Enflow is a digital creative agency based in Alphen aan den Rijn, Netherlands. We specialize in developing web applications, mobile applications and websites. You can find more info [on our website](https://enflow.nl/en).
