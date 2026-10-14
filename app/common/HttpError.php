<?php
/** FOURWARD coursework | Author: 严苑毓 | HTTP validation and permission errors. */
declare(strict_types=1);
namespace Fourward\common;

final class HttpError extends \RuntimeException {
  public function __construct(public readonly int $status, string $message) {
    parent::__construct($message);
  }
}
