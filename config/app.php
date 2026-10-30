<?php
// Configuración general de la aplicación.
define('APP_NAME', 'Préstamos de Equipos');
define('BASE_URL', '/prestamos-equipos');   // Carpeta del proyecto dentro de htdocs, sin "/" al final
define('MULTA_POR_DIA', 500);               // Colones por cada día de retraso
define('DIAS_PRESTAMO_DEFECTO', 3);         // Plazo sugerido al registrar un préstamo

date_default_timezone_set('America/Costa_Rica');

   ini_set('display_errors', '1');
   error_reporting(E_ALL);
