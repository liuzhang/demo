<?php
require_once 'Http.php';

$socket = stream_socket_server("0.0.0.0:2345", $errno, $errstr);
stream_set_blocking($socket, 0);

if (!$socket) {
    echo "$errstr ($errno)<br />\n";
} else {
    while (true) {
        $conn = @stream_socket_accept($socket);

        if ($conn)
        {
            $data = Http::encode('Hi world');
            fwrite($conn, $data);
            fclose($conn);

        } else {
            echo "no newSocket\n";
        }
    }
}