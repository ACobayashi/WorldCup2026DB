<?php
/** FOURWARD coursework | Author: 韩羽宸 | 学号: 2410673 | Venue catalog view. */
declare(strict_types=1);
?>
<?php if (!$data['items']): ?><section class="wc-empty"><p class="wc-eyebrow">VENUES</p><h2>场馆资料尚未导入</h2><p>场馆名称、城市与所在时区将在资料核实后展示。</p></section><?php else: ?>
  <div class="wc-grid"><?php foreach ($data['items'] as $venue): ?><article class="wc-card"><p class="wc-eyebrow"><?= e($venue['country']) ?></p><h2><?= e($venue['name']) ?></h2><p><?= e($venue['city']) ?></p><p class="wc-muted"><?= e($venue['timezone']) ?></p><p class="wc-muted">资料来源：<?= e($venue['source_url'] ?? '待补充') ?></p></article><?php endforeach; ?></div>
<?php endif; ?>
