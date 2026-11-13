<?php
/** 作者：林子媛 | 学号：2410936 */
declare(strict_types=1);
$roleLabels = ['admin' => '管理员', 'member' => '团队成员', 'user' => '普通用户'];
?>
<?php if ($user): ?>
  <section class="portal-panel portal-account-summary"><div><p class="portal-eyebrow">MY ACCOUNT</p><h2><?= e($user['username']) ?></h2><span class="portal-muted"><?= e($roleLabels[$user['role']] ?? '普通用户') ?></span></div><form action="account.php" method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button class="glass-button" type="submit">退出登录</button></form></section>
  <div class="portal-grid portal-section">
    <?php if (in_array($user['role'], ['admin', 'member'], true)): ?>
      <a class="portal-panel portal-service" href="workspace.php"><span>01 / WORKSPACE</span><h2>内容工作台 ↗</h2><p>编辑团队与个人资料</p></a>
      <a class="portal-panel portal-service" href="workspace.php?section=files"><span>02 / COURSEWORK</span><h2>上传与发布作业 ↗</h2><p>上传文件、发布下载链接、管理草稿</p></a>
      <a class="portal-panel portal-service" href="workspace.php?section=stats"><span>03 / CHARTS</span><h2>数据图表 ↗</h2><p>作业提交与发布统计</p></a>
      <?php if ($user['role'] === 'admin'): ?><a class="portal-panel portal-service" href="admin.php?status=pending#review-heading"><span>04 / MODERATION</span><h2>留言审核 ↗</h2><p>待审核、已公开、未通过</p></a><?php else: ?><section class="portal-panel portal-service is-locked"><span>04 / MODERATION</span><h2>留言审核</h2><p>仅管理员账号可审核</p></section><?php endif; ?>
      <a class="portal-panel portal-service" href="admin.php#mottos-heading"><span>05 / MOTTO</span><h2>编辑座右铭 ↗</h2></a>
    <?php endif; ?>
    <a class="portal-panel portal-service" href="coursework.php"><span>DOWNLOADS</span><h2>课程作业下载 ↗</h2></a>
  </div>
<?php else: ?>
  <section class="portal-panel portal-auth">
    <p class="portal-eyebrow"><?= $mode === 'register' ? 'JOIN THE STANDS' : 'WELCOME BACK' ?></p><h2><?= $mode === 'register' ? '注册普通用户' : '回到我们的主场' ?></h2>
    <?php if ($errorMessage): ?><p class="portal-feedback is-error" role="alert"><?= e($errorMessage) ?></p><?php endif; ?>
    <form method="post" action="account.php?<?= e(http_build_query(['mode' => $mode])) ?>" class="portal-form">
      <input type="hidden" name="action" value="<?= e($mode) ?>"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="return" value="<?= e($returnPath) ?>">
      <label>账号<input name="username" autocomplete="username" maxlength="48" value="<?= e($username) ?>" required><?php if ($mode === 'register'): ?><small>3–48 位英文字母、数字、下划线或短横线</small><?php endif; ?></label>
      <label>密码<input name="password" type="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" maxlength="<?= $mode === 'register' ? '72' : '256' ?>" required><?php if ($mode === 'register'): ?><small>12–72 字节</small><?php endif; ?></label>
      <?php if ($mode === 'register'): ?><label>确认密码<input name="password_confirmation" type="password" autocomplete="new-password" maxlength="72" required></label><?php endif; ?>
      <button class="primary-button" type="submit"><?= $mode === 'register' ? '注册并继续' : '登录并继续' ?></button>
    </form>
    <p><a class="portal-text-link" href="account.php?<?= e(http_build_query(['mode' => $mode === 'register' ? 'login' : 'register', 'return' => $returnPath])) ?>"><?= $mode === 'register' ? '已有账号？前往登录' : '还没有账号？注册普通用户' ?></a></p>
  </section>
<?php endif; ?>
