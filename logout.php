<?php
session_start();
session_destroy();
header("Location: saisie/login.php");
exit();
?>