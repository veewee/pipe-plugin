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

### Plugin issue detections

The plugin validates if the types inside the stages of the pipe combinator line up.
It also validates the amount of arguments!

```shell
./vendor/bin/psalm --no-cache tests/invalid-stages.php
./vendor/bin/psalm --no-cache tests/argument-issues.php
```

### Empty pipe

See https://github.com/vimeo/psalm/issues/7244
Currently, templated arguments are not being resolved in closures / callables
For now, we fall back to the built-in types.

```shell
php tests/empty-pipe.php
php tests/empty-pipe2.php

./vendor/bin/psalm --no-cache tests/empty-pipe.php
./vendor/bin/psalm --no-cache tests/empty-pipe2.php
```

### Other structures

#### TODO

These are not yet supported - since it is hard to get this type information from a plugin.

* First-class function callable
* New_ invokable classes
* variables pointing to invokables / FunctionLikes


```
php ./vendor/bin/psalm --no-cache tests/functionlike.php
```