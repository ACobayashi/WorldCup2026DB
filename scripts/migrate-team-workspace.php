<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/common/bootstrap.php';
Fourward\common\Schema::install(database());
echo "Account roles, profiles and coursework schema ready; existing data preserved.\n";
