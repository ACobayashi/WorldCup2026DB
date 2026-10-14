<?php
/** FOURWARD coursework | Author: 严苑毓 | Non-destructive schema initialization CLI. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
database()->exec(file_get_contents(PROJECT_ROOT . '/data/install.sql'));
echo "Guestbook schema initialized; existing messages, mottos and accounts preserved.\n";
