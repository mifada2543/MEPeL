<?php 
session_name('mepel');
session_start();
session_destroy();
header("Location: ../index.php");