<?php

$conn = mysqli_connect('localhost', 'root', '', 'talaue_88');

// For development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Production (When website goes live)
// mysqli_report(MYSQLI_REPORT_OFF);

if (!$conn) {
    die("COMMUNICATION ERROR: " . mysqli_connect_error());
}