<?php

foreach ([
    '/tmp/views',
    '/tmp/cache',
    '/tmp/sessions',
] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
}

require __DIR__.'/../public/index.php';
