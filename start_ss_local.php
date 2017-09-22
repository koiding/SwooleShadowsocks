<?php
include "LocalProxy.php";

$conf = include "conf.php";

$local_server = new LocalProxy($conf);
$local_server->start();

