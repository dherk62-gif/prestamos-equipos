<?php
require __DIR__ . '/includes/init.php';

$_SESSION = [];
session_destroy();
session_start();
flash('success', 'Cerró la sesión correctamente.');
redirect('login.php');
