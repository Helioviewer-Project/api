# Testing
This directory is used for unit testing back end Helioviewer Functions
using PHPUnit.

## Setup
This setup uses [composer](https://getcomposer.org/) to get
php unit. You can usually get composer with your package manager. If
it's not available as a package, then you can get it from the website
linked above. After getting composer, run:
```bash
composer install
```

This will create a vendor folder with phpunit stored at
`vendor/bin/phpunit` You can execute phpunit from that full path, or
you can add `$PWD/vendor/bin` to your path.

You can find a reference for PHPUnit
[here](https://phpunit.readthedocs.io/en/9.5/)

## Legacy Tests
The directory `legacy_tests` contains some older tests that were
written to exercise certain functions, but they do not perform any
assertions on the correctness of the functions. Because of this,
they're useful for reference on how to initialize and execute
functions in a test, but newer tests should be written with PHPUnit
where we can make assertions about the correctness of the code we're
executing.


