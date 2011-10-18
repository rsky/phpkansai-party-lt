<?php
chdir(__DIR__);
require_once './KQ.php';

$kq = new KQ\Driver();
echo $kq->doorWillClose(file_get_contents('./kq-data.txt')), "\n";
