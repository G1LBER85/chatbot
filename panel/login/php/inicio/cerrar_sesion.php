<?php
session_start();
session_unset();
session_destroy();

$status = isset($_GET['status']) ? $_GET['status'] : '';

if ($status === 'expirado') {
    header("Location: ../../index.php?status=expirado");
} else {
    header("Location: ../../index.php");
}
exit();
?>