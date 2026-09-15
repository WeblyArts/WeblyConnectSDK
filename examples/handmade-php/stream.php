<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

/** @var \WeblyConnect\Sdk\Site\SiteConnect $connect */
$connect = require __DIR__ . '/config/site-connect.php';

$connect->handleStreamRequest();
