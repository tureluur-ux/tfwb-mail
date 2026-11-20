<?php
require __DIR__.'/lib.php';
boot();

if($_SERVER['REQUEST_METHOD']!=='POST'){
 header('Location: login.php');
 exit;
}
csrf_check();

session_destroy();
@unlink(__DIR__.'/data/tbmail.sqlite');
@unlink(__DIR__.'/data/.key');

header('Location: login.php');
