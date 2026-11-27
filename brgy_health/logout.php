<?php require 'config.php';if(me())audit('Logout');session_destroy();header('Location: login.php');
