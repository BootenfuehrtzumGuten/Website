<?php
session_start();
session_destroy();
header('Location: kunden_login.php');
exit;
