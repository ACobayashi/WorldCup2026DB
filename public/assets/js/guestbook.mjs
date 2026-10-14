import { MESSAGE_STORAGE_KEY, normalizeMessages, createMessage } from './messages.mjs';

const $ = (id) => document.getElementById(id);

export function createGuestbook({ members, notify, onMottosChange }) {
  const serverMode = document.querySelector('meta[name="guestbook-mode"]')?.content === 'server';
  const ids = ['team', ...members.map((member) => member.id)];
  const names = new Map([['team', '整个团队'], ...members.map((member) => [member.id, member.name])]);
  let localMessages = [];
  try { localMessages = normalizeMessages(JSON.parse(localStorage.getItem(MESSAGE_STORAGE_KEY) || '[]'), ids); } catch { /* Storage may be unavailable. */ }
  let mottos = {};
  let csrfToken;
  let bootstrapPromise;
  let selectedMember = members[0];
  let listVersion = 0;
  let previewVersion = 0;
  let standsVersion = 0;
  let page = 1;
  let submitting = false;
  let listLoading = false;

  async function request(action, options = {}) {
    let response;
    try { response = await fetch('api.php?action=' + action, { credentials: 'same-origin', ...options }); }
    catch { throw new Error('网络连接失败，请检查连接后重试。'); }
    let body;
    try { body = await response.json(); } catch { throw new Error('留言服务暂时不可用，请稍后重试。'); }
    if (!response.ok || body.ok !== true) throw new Error(body.error?.message || '留言服务暂时不可用，请稍后重试。');
    return body.data;
  }

  function bootstrap() {
    if (!serverMode) return Promise.resolve();
    if (!bootstrapPromise) {
      bootstrapPromise = request('bootstrap').then((data) => {
        csrfToken = data.csrfToken;
        mottos = data.mottos;
        onMottosChange();
      }).catch((error) => { bootstrapPromise = null; throw error; });
    }
    return bootstrapPromise;
  }

  async function listing(recipient, requestedPage = 1) {
    if (serverMode) return request('messages&recipient=' + encodeURIComponent(recipient) + '&page=' + requestedPage);
    const entries = localMessages.filter((entry) => entry.memberId === recipient).slice().reverse();
    return { items: entries.slice((requestedPage - 1) * 20, requestedPage * 20), total: entries.length, page: requestedPage, pageSize: 20 };
  }

  function showEntries(result, recipient) {
    $('message-count').textContent = result.total + ' 条';
    $('message-list').replaceChildren();
    $('message-page').textContent = '第 ' + result.page + ' 页';
    $('message-prev').disabled = result.page <= 1;
    $('message-next').disabled = result.page * result.pageSize >= result.total;
    $('message-pagination').hidden = result.total <= result.pageSize;
    if (!result.items.length) {
      const empty = document.createElement('p');
      empty.className = 'empty-messages';
      empty.textContent = serverMode ? '还没有公开留言。留下一句问候，审核通过后会出现在这里。' : '这里还很安静。演示留言只会留在当前浏览器。';
      $('message-list').append(empty);
    }
    result.items.forEach((entry) => {
      const article = document.createElement('article');
      article.className = 'saved-message';
      const heading = document.createElement('div');
      const author = document.createElement('strong');
      author.textContent = entry.author;
      const time = document.createElement('time');
      time.dateTime = entry.createdAt;
      time.textContent = new Intl.DateTimeFormat('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(entry.createdAt));
      const body = document.createElement('p');
      body.textContent = entry.message;
      heading.append(author, time);
      article.append(heading, body);
      $('message-list').append(article);
    });
    $('message-list-heading').textContent = recipient === 'team' ? '给团队的留言' : '给' + names.get(recipient) + '的留言';
  }

  async function renderMessages() {
    const version = ++listVersion;
    const recipient = $('message-member').value;
    $('message-dialog-title').textContent = recipient === 'team' ? '给我们的团队留言' : '给' + names.get(recipient) + '留言';
    listLoading = true;
    $('message-list').textContent = '正在读取留言…';
    $('message-count').textContent = '…';
    $('message-pagination').hidden = true;
    try {
      const result = await listing(recipient, page);
      if (version === listVersion) showEntries(result, recipient);
    } catch (error) {
      if (version === listVersion) {
        $('message-list').textContent = error.message;
        $('message-count').textContent = '读取失败';
      }
    } finally { if (version === listVersion) listLoading = false; }
  }

  async function refreshPreview() {
    const member = selectedMember;
    const version = ++previewVersion;
    try {
      const result = await listing(member.id);
      if (version !== previewVersion) return;
      $('message-preview-text').textContent = result.total
        ? result.total + ' 条' + (serverMode ? '公开' : '本地演示') + '留言 · ' + result.items[0].message
        : '还没有给' + member.name + '的' + (serverMode ? '公开' : '演示') + '留言，来打个招呼吧。';
    } catch { if (version === previewVersion) $('message-preview-text').textContent = '暂时无法读取留言，请稍后再试。'; }
  }

  async function refreshTeamStands() {
    const version = ++standsVersion;
    const list = $('team-stands-list');
    list.setAttribute('aria-busy', 'true');
    $('refresh-team-stands').hidden = true;
    try {
      const result = await listing('team');
      if (version !== standsVersion) return;
      list.replaceChildren();
      if (!result.items.length) {
        const empty = document.createElement('p');
        empty.className = 'stands-empty';
        empty.textContent = serverMode ? '主场的第一声问候，等你来留下' : '还没有本地演示留言，来打个招呼吧';
        list.append(empty);
      }
      result.items.slice(0, 3).forEach((entry) => {
        const card = document.createElement('article');
        card.className = 'stands-card';
        const message = document.createElement('p');
        message.textContent = entry.message;
        const metadata = document.createElement('div');
        const author = document.createElement('strong');
        author.textContent = entry.author;
        const time = document.createElement('time');
        time.dateTime = entry.createdAt;
        time.textContent = new Intl.DateTimeFormat('zh-CN', { timeZone: 'Asia/Shanghai', month: '2-digit', day: '2-digit' }).format(new Date(entry.createdAt));
        metadata.append(author, time);
        card.append(message, metadata);
        list.append(card);
      });
    } catch {
      if (version !== standsVersion) return;
      list.textContent = '暂时无法读取场边留言';
      $('refresh-team-stands').hidden = false;
    } finally {
      if (version === standsVersion) list.setAttribute('aria-busy', 'false');
    }
  }

  function open(recipient) {
    $('message-member').value = recipient;
    $('message-error').textContent = '';
    page = 1;
    renderMessages();
    $('message-dialog').showModal();
  }

  for (const [id, name] of names) {
    const option = document.createElement('option');
    option.value = id;
    option.textContent = name;
    $('message-member').append(option);
  }
  $('message-notice').textContent = serverMode
    ? '留下一句问候吧。留言审核通过后会公开展示，请勿填写联系方式等隐私信息。'
    : '本地演示：留言仅保存在当前浏览器，不会发送到团队，也不经过后台审核。';
  $('message-source-label').textContent = serverMode ? 'GUESTBOOK' : 'LOCAL DEMO';
  $('message-submit').firstChild.textContent = serverMode ? '提交留言 ' : '保存演示留言 ';
  $('team-workspace').hidden = !serverMode;
  $('open-message').addEventListener('click', () => open(selectedMember.id));
  $('view-messages').addEventListener('click', () => open(selectedMember.id));
  $('open-team-message').addEventListener('click', () => open('team'));
  $('open-team-stands').addEventListener('click', () => open('team'));
  $('refresh-team-stands').addEventListener('click', refreshTeamStands);
  if (!serverMode) $('team-stands-caption').textContent = '本地演示 · 这些留言仅保存在当前浏览器';
  $('message-member').addEventListener('change', () => { page = 1; renderMessages(); });
  $('message-prev').addEventListener('click', () => { if (!listLoading && page > 1) { page -= 1; renderMessages(); } });
  $('message-next').addEventListener('click', () => { if (!listLoading) { page += 1; renderMessages(); } });
  $('message-content').addEventListener('input', (event) => {
    $('message-length').textContent = Array.from(event.target.value).length + ' / 280';
    $('message-error').textContent = '';
  });
  $('message-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitting) return;
    submitting = true;
    $('message-submit').disabled = true;
    for (const id of ['message-member', 'message-author', 'message-content']) $(id).disabled = true;
    $('message-error').textContent = '';
    try {
      const recipient = $('message-member').value;
      if (!ids.includes(recipient)) throw new Error('请选择有效的留言对象。');
      const entry = createMessage(recipient, $('message-author').value, $('message-content').value);
      if (serverMode) {
        await bootstrap();
        const result = await request('submit', {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify(entry),
        });
        notify(result.notice);
        $('message-error').textContent = result.notice;
      } else {
        const next = normalizeMessages([...localMessages, entry], ids);
        localStorage.setItem(MESSAGE_STORAGE_KEY, JSON.stringify(next));
        localMessages = next;
        notify('演示留言已保存在当前浏览器。');
      }
      $('message-content').value = '';
      $('message-length').textContent = '0 / 280';
      page = 1;
      await renderMessages();
      refreshPreview();
      refreshTeamStands();
    } catch (error) {
      $('message-error').textContent = error.message;
    } finally {
      submitting = false;
      $('message-submit').disabled = false;
      for (const id of ['message-member', 'message-author', 'message-content']) $(id).disabled = false;
    }
  });

  return {
    selectMember(member) { selectedMember = member; refreshPreview(); },
    motto(id) { return mottos[id]; },
    async init() {
      try { await bootstrap(); } catch { notify('留言服务暂时不可用，请稍后重试。'); }
      await refreshTeamStands();
    },
  };
}
