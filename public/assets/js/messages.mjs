export const MESSAGE_STORAGE_KEY = 'fourward-member-messages-v1';

export function normalizeMessages(value, allowedIds) {
  if (!Array.isArray(value)) return [];
  return value.filter((entry) => (
    entry && typeof entry === 'object'
    && allowedIds.includes(entry.memberId)
    && typeof entry.author === 'string'
    && typeof entry.message === 'string'
    && entry.message.trim().length > 0
    && Array.from(entry.message.trim()).length <= 280
    && typeof entry.createdAt === 'string'
    && Number.isFinite(Date.parse(entry.createdAt))
  )).slice(-80).map((entry) => ({
    memberId: entry.memberId,
    author: Array.from(entry.author.trim()).slice(0, 24).join('') || '一位访客',
    message: entry.message.trim(),
    createdAt: entry.createdAt,
  }));
}

export function createMessage(memberId, author, message, createdAt = new Date().toISOString()) {
  const text = message.trim();
  if (!text || Array.from(text).length > 280) throw new Error('请填写 1 至 280 字的留言。');
  if (Array.from(author.trim()).length > 24) throw new Error('称呼最多 24 字。');
  return { memberId, author: author.trim() || '一位访客', message: text, createdAt };
}
