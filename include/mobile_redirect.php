<?php
    $current_uri = $_SERVER['REQUEST_URI'];
    $redirect_uri = str_replace('iphone', 'mobile', $current_uri);
    header('Location: ' . $redirect_uri);
