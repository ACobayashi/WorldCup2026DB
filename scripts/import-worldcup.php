<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Local-only World Cup import CLI. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../app/common/bootstrap.php';
try {
  if (!isset($argv[1]) || !is_file($argv[1])) throw new InvalidArgumentException('Usage: php scripts/import-worldcup.php dataset.json [--dry-run]');
  $content=file_get_contents($argv[1]);
  $data=json_decode($content,true,512,JSON_THROW_ON_ERROR);
  $result=(new Fourward\worldcup\services\DataImporter(database()))->import($data,hash('sha256',$content),in_array('--dry-run',$argv,true));
  echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch (Throwable $error) {
  fwrite(STDERR,'Import failed; transaction rolled back: '.$error->getMessage()."\n"); exit(1);
}
