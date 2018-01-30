Rakit Console
=======================

Rakit Console is simple PHP library to create Command Line Interface (CLI) Application.
This library strongly inspired by [Laravel Artisan Console](https://laravel.com/docs/5.4/artisan).

## Features

* Closure command. You don't need to create class for simple command.
* Built-in command `list`.
* Auto help handler for each commands.
* Easy command signature.
* Password input.
* Simple Coloring.

## Installation

Just run this composer command:

```bash
composer require rakit/console
```

## Quickstart

#### 1. Create App

Create a file named `cli` (without extension).

```php
<?php

use Rakit\Console\App;

require('vendor/autoload.php');

// 1. Initialize app
$app = new App;

// 2. Register commands
$app->command('hello {name}', 'Say hello to someone', function($name) {
    $this->writeln("Hello {$name}");
});

// 3. Run app
$app->run();
```

#### 2. Running Command

Open terminal/cmd, go to your app directory, run this command:

```
php cli hello "John Doe"
```

#### 3. Command List

You can see available commands by typing this:

```
php cli list
```

#### 4. Show Help

You can show help by putting `--help` or `-h` for each command. For example:

```
php cli hello --help
```
