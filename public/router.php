<?php
/** FOURWARD coursework | Author: 严苑毓 | Local PHP server router, public files only. */
declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/' || $path === '/index.html') {
  require __DIR__ . '/index.php';
  return true;
}
if ($path === '/team' || $path === '/team/') {
  header('Location: /', true, 302);
  return true;
}
if ($path === '/worldcup') {
  $query = $_SERVER['QUERY_STRING'] ?? '';
  header('Location: /worldcup/' . ($query !== '' ? '?' . $query : ''), true, 302);
  return true;
}
if ($path === '/worldcup/') {
  require __DIR__ . '/worldcup/index.php';
  return true;
}
return false;
