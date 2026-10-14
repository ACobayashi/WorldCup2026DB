<?php
/** FOURWARD coursework | Author: 严苑毓 | Serve the shared homepage in database mode. */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo str_replace('name="guestbook-mode" content="demo"', 'name="guestbook-mode" content="server"', file_get_contents(__DIR__ . '/index.html'));
