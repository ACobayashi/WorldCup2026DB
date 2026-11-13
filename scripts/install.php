<?php
/** FOURWARD coursework | Author: 严苑毓 | 学号: 2413682 | Non-destructive schema initialization CLI. */
/** 资料结构兼容：林子媛 | 学号：2410936 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
Fourward\common\Schema::install(database());
echo "Team and World Cup schemas initialized; existing records preserved.\n";
