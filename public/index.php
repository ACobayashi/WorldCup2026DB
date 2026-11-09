<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Serve the shared homepage in database mode. */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo str_replace(
  ['name="guestbook-mode" content="demo"', 'href="downloads.html"'],
  ['name="guestbook-mode" content="server"', 'href="downloads.php"'],
  file_get_contents(__DIR__ . '/index.html')
);
