<?php
chdir(__DIR__);
require_once './KQ.php';

$code = <<<EOS
+++++++++[>
++++++++>++
+++++++++>+
++++<<<-]>.>
++.+++++++.
.+++.>-.----
--------.<+++
+++++.-------
-.+++.------.
--------.>+.
EOS;
$kq = new KQ\Driver();
$string = $kq->encode($code);
echo $string, "\n";
//$kq->doorWillClose($string);
