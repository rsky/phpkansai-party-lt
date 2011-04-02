<?php
chdir(__DIR__);
require_once './BrainDog.php';

$dog = new BrainDog();
$dog->bow(file_get_contents('./bd-data.txt'));
