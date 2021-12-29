# Installation

```shell
git clone https://github.com/veewee/pipe-plugin.git
composer install
```

# Where to find what?

- [pipe](https://github.com/veewee/pipe-plugin/blob/main/src/pipe.php)
- [Plugin](https://github.com/veewee/pipe-plugin/blob/main/src/Psalm/Plugin.php)
- [Plugin listener](https://github.com/veewee/pipe-plugin/blob/main/src/Psalm/PipeArgumentsProvider.php)

## Run analysis

### Empty pipe

This currently results in the issue in which the template parameters are not being replaced by the value provided in the first argument of the `FuncCall`.

```shell
php tests/empty-pipe.php

./vendor/bin/psalm --no-cache tests/empty-pipe.php
```

```shell
php tests/empty-pipe2.php

./vendor/bin/psalm --no-cache tests/empty-pipe2.php
```

### Other plugin inner-workings

The plugin validates if the types inside the stages of the pipe combinator line up.
It also validates the amount of arguments!

```shell
./vendor/bin/psalm --no-cache tests/invalid-stages.php
./vendor/bin/psalm --no-cache tests/argument-issues.php
```


### TODO:

Closures vs callables - PSL issue:
https://github.com/azjezz/psl/issues/329

(!) This crashes psalm:

```
Uncaught AssertionError: assert(!$this->isFirstClassCallable()) in vendor/nikic/php-parser/lib/PhpParser/Node/Expr/CallLike.php:36
```

```
php ./vendor/bin/psalm --no-cache tests/functionlike.php
```