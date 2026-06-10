<?php
// Redirect to the actual forgot password page
header('Location: ' . (isset($_SERVER['REQUEST_SCHEME']) 
    ? $_SERVER['REQUEST_SCHEME'] 
    : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/general/forgot_password.php');
exit;
?>