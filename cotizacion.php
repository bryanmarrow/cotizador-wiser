<?php
/**
 * Redirección simple para URL limpia
 * Este archivo actúa como un proxy hacia cotizacion-publica.php
 */

// Capturar todos los parámetros GET
$queryString = $_SERVER['QUERY_STRING'] ?? '';

// Incluir el archivo de destino
include 'cotizacion-publica.php';
?>