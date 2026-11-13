<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
$editable = array_filter($profiles, fn($row) => \Fourward\team\models\TeamProfile::canEdit($user, $row['member_slug']));
$counts = array_fill_keys(array_keys($editable), ['draft' => 0, 'published' => 0]);
foreach ($stats['files'] as $row) if (isset($counts[$row['owner_slug']])) $counts[$row['owner_slug']][$row['status']] = (int) $row['total'];
$formValue = static fn(string $key, string $default = ''): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
$linkMode = $formValue('source_type') === 'link';
?>
<nav class="portal-tabs" aria-label="内容管理分类"><?php foreach (['profile' => '团队与个人资料', 'files' => '作业上传与发布', 'stats' => '数据图表'] as $key => $label): ?><a href="workspace.php?section=<?= e($key) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<?php if ($errorMessage): ?><p class="portal-feedback is-error" role="alert"><?= e($errorMessage) ?></p><?php endif; ?>
<?php if ($flash): ?><p class="portal-feedback" role="status"><?= e($flash) ?></p><?php endif; ?>
<?php if ($section === 'profile'): ?>
  <div class="portal-split">
    <aside class="portal-panel"><p class="portal-eyebrow">PROFILE EDITOR</p><h2>我们的队伍</h2><nav class="portal-member-nav" aria-label="选择编辑对象"><?php foreach ($editable as $target => $row): ?><a href="workspace.php?member=<?= e($target) ?>" <?= $slug === $target ? 'aria-current="page"' : '' ?>><?= e($row['display_name']) ?><?= $target === 'team' ? ' · 团队介绍' : '' ?></a><?php endforeach; ?></nav><a class="portal-text-link" href="./#<?= $slug === 'team' ? 'team' : 'members' ?>">查看前台展示</a></aside>
    <section class="portal-panel"><h2><?= $slug === 'team' ? '编辑团队介绍' : '编辑个人档案' ?></h2><form method="post" class="portal-form">
      <input type="hidden" name="action" value="profile"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="member_slug" value="<?= e($slug) ?>">
      <label><?= $slug === 'team' ? '团队名称' : '姓名' ?><input name="display_name" maxlength="40" value="<?= e($profile['display_name']) ?>" required></label>
      <?php if ($slug === 'team'): ?>
        <label>首页主标题<textarea name="headline" maxlength="100" rows="2" required><?= e($profile['headline']) ?></textarea><small>最多两行，100 字以内</small></label>
        <input type="hidden" name="school" value=""><input type="hidden" name="role_title" value=""><input type="hidden" name="github_url" value="">
      <?php else: ?>
        <label>学院<input name="school" maxlength="80" value="<?= e($profile['school']) ?>"></label><label>分工<input name="role_title" maxlength="80" value="<?= e($profile['role_title']) ?>"></label><label>GitHub 主页<input name="github_url" type="url" maxlength="255" placeholder="https://github.com/username" value="<?= e($profile['github_url']) ?>"></label><input type="hidden" name="headline" value="">
      <?php endif; ?>
      <label><?= $slug === 'team' ? '团队介绍' : '个人简介与工作说明' ?><textarea name="biography" maxlength="280" rows="4" required><?= e($profile['biography']) ?></textarea><small>280 字以内</small></label>
      <button class="primary-button" type="submit">保存并更新前台</button>
    </form></section>
  </div>
<?php elseif ($section === 'files'): ?>
  <section class="portal-panel"><p class="portal-eyebrow">COURSEWORK SUBMISSION</p><h2>提交课程作业</h2><p>保存草稿后，在下方发布文件或链接。</p>
    <form method="post" enctype="multipart/form-data" class="portal-form portal-upload">
      <input type="hidden" name="action" value="upload"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="MAX_FILE_SIZE" value="52428800">
      <label>提交方式<select name="source_type" id="upload-source"><option value="file">上传文件</option><option value="link" <?= $linkMode ? 'selected' : '' ?>>外部下载链接</option></select></label>
      <label>作业归属<select name="owner_slug" id="upload-owner"><?php foreach ($editable as $target => $row): ?><option value="<?= e($target) ?>" <?= $formValue('owner_slug') === $target ? 'selected' : '' ?>><?= $target === 'team' ? '团队作业' : e($row['display_name']) . ' · 个人作业' ?></option><?php endforeach; ?></select></label>
      <label>作业编号<select name="assignment_no" id="upload-assignment"><?php foreach ($labels[$user['role'] === 'admin' ? 'team' : 'personal'] as $number => $label): ?><option value="<?= e($number) ?>" <?= $formValue('assignment_no') === (string) $number ? 'selected' : '' ?>><?= e($number . ' · ' . $label) ?></option><?php endforeach; ?></select></label>
      <label>作业标题<input name="title" maxlength="120" value="<?= e($formValue('title')) ?>" required></label>
      <label id="upload-file-field" <?= $linkMode ? 'hidden' : '' ?>>选择文件<input name="coursework" type="file" accept=".pdf,.zip,.docx,.pptx,.mp4" <?= $linkMode ? 'disabled' : 'required' ?>><small>PDF / ZIP / DOCX / PPTX / MP4，最大 50 MiB</small></label>
      <label id="upload-link-field" <?= $linkMode ? '' : 'hidden' ?>>下载链接<input name="external_url" type="url" maxlength="2048" placeholder="https://…" value="<?= e($formValue('external_url')) ?>" <?= $linkMode ? 'required' : 'disabled' ?>><small>HTTPS 外部下载地址</small></label>
      <button class="primary-button" type="submit">保存为草稿</button>
    </form>
    <script type="application/json" id="coursework-labels"><?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
  </section>
  <section class="portal-section"><h2>我的作业</h2><?php if (!$files): ?><p class="portal-muted">暂无作业</p><?php endif; ?><div class="portal-grid">
    <?php foreach ($files as $file): ?><article class="portal-panel portal-file"><p class="portal-eyebrow"><?= e($profiles[$file['owner_slug']]['display_name']) ?> / <?= e($file['assignment_no']) ?></p><h3><?= e($file['title']) ?></h3><p><?= e($file['original_name']) ?></p><small><?= $file['source_type'] === 'link' ? '外部链接' : e(number_format($file['file_size'] / 1024, 1)) . ' KiB' ?> · <?= $file['status'] === 'published' ? '已发布' : '草稿' ?></small><div class="portal-actions"><a class="glass-button" href="download.php?id=<?= e($file['id']) ?>"><?= $file['source_type'] === 'link' ? '核对链接' : '下载核对' ?></a><form method="post"><input type="hidden" name="action" value="publish"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($file['id']) ?>"><button class="glass-button" name="status" value="<?= $file['status'] === 'published' ? 'draft' : 'published' ?>" type="submit"><?= $file['status'] === 'published' ? '撤回为草稿' : '发布到前台' ?></button></form></div></article><?php endforeach; ?>
  </div></section>
<?php else: ?>
  <p id="chart-empty" class="portal-feedback" <?= array_sum(array_column($stats['files'], 'total')) > 0 ? 'hidden' : '' ?>>当前暂无作业</p>
  <section class="portal-panel"><div class="portal-actions"><div><p class="portal-eyebrow">ECHARTS / DATABASE</p><h2>作业提交与发布情况</h2></div><label>统计状态<select id="chart-status"><option value="all">全部作业</option><option value="published">已发布</option><option value="draft">草稿</option></select></label><button class="glass-button" id="refresh-charts" type="button">刷新统计</button></div><p id="chart-feedback" role="status" aria-live="polite"></p><div id="coursework-chart" class="portal-chart" role="img" aria-label="各成员作业数量条形图"></div><div class="portal-table-wrap"><table class="portal-table"><caption>作业数量</caption><thead><tr><th>归属</th><th>草稿</th><th>已发布</th><th>合计</th></tr></thead><tbody id="chart-table"><?php foreach ($editable as $owner => $row): ?><tr><td><?= e($row['display_name']) ?></td><td><?= e($counts[$owner]['draft']) ?></td><td><?= e($counts[$owner]['published']) ?></td><td><?= e(array_sum($counts[$owner])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
  <?php if ($stats['roles'] !== null): ?><section class="portal-panel portal-section"><h2>账号身份分布</h2><div id="account-chart" class="portal-chart" role="img" aria-label="普通用户、团队成员与管理员数量分布"></div><ul><?php foreach (['user' => '普通用户', 'member' => '团队成员', 'admin' => '管理员'] as $key => $label): ?><li><?= e($label) ?>：<span data-role-count="<?= e($key) ?>"><?= e($stats['roles'][$key] ?? 0) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  <script type="application/json" id="workspace-chart-data"><?= json_encode(['stats' => $stats, 'owners' => array_map(fn($row) => $row['display_name'], $editable)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?>
