<?php

if (isset($_SERVER['REQUEST_URI']) && ! str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
    $_SERVER['REQUEST_URI'] = '/api'.$_SERVER['REQUEST_URI'];
}

if (isset($_SERVER['PATH_INFO']) && ! str_starts_with($_SERVER['PATH_INFO'], '/api/')) {
    $_SERVER['PATH_INFO'] = '/api'.$_SERVER['PATH_INFO'];
}

require __DIR__.'/index.php';
