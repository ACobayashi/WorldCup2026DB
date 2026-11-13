<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
require __DIR__ . '/../app/common/bootstrap.php';
Fourward\common\Portal::run(static fn() => (new Fourward\team\controllers\CourseworkController(database()))->index());
