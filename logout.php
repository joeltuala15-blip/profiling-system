<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php?success=logout_success");
exit();
?>














