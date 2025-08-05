<?php
function log_event($message)
{
    global $logFile;
    $date = date('Y-m-d H:i:s');
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $logLine = "[$date] [$remoteAddr] $message\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}
