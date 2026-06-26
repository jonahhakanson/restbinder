<?php
require __DIR__ . '/../src/bootstrap.php';

use RestBinder\Http\ApiController;
use RestBinder\Store\FileResourceStore;

$config = require __DIR__ . '/../config/app.php';
$controller = new ApiController(new FileResourceStore($config['store_path']), $config['usrb_version']);
$controller->handle();
