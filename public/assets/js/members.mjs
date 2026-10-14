export const members = Object.freeze([
  {
    id: 'lin-ziyuan',
    name: '林子媛',
    english: 'LIN ZIYUAN',
    shortName: 'ZIYUAN',
    gender: 'female',
    school: '密码与网络空间安全学院',
    role: '团队主页 · 页面与交互',
    task: '搭建团队展示页面，完善成员切换、视觉和交互效果',
    quote: '一个有趣且靠谱的人。',
    tags: ['密码安全', '页面交互'],
    accent: '#83b7f3',
    github: 'https://github.com/ACobayashi',
    portrait: 'assets/images/members/lin-ziyuan.png',
    portraitFocus: 0.62,
  },
  {
    id: 'yan-yuanyu',
    name: '严苑毓',
    english: 'YAN YUANYU',
    shortName: 'YUANYU',
    gender: 'female',
    school: '密码与网络空间安全学院',
    role: '团队主页 · 内容与后台',
    task: '完善成员信息、内容管理与留言功能，连接前台和后台',
    quote: '天行健，君子以自强不息',
    tags: ['密码安全', '内容管理'],
    accent: '#abd4ef',
    github: 'https://github.com/yanyuanyu',
    portrait: 'assets/images/members/yan-yuanyu.png',
    portraitFocus: 0.51,
  },
  {
    id: 'ma-yuxiang',
    name: '马禹翔',
    english: 'MA YUXIANG',
    shortName: 'YUXIANG',
    gender: 'male',
    school: '计算机学院',
    role: '世界杯项目 · 赛事与数据库',
    task: '负责比赛、赛程与数据库设计',
    quote: '丢掉幻想 准备斗争',
    tags: ['计算机', '赛事数据'],
    accent: '#8ba8eb',
    github: 'https://github.com/s1nk141',
    portrait: 'assets/images/members/ma-yuxiang.png',
    portraitFocus: 0.50,
  },
  {
    id: 'han-yuchen',
    name: '韩羽宸',
    english: 'HAN YUCHEN',
    shortName: 'YUCHEN',
    gender: 'male',
    school: '计算机学院',
    role: '世界杯项目 · 球队与部署',
    task: '完善球队数据与部署验证',
    quote: '命数如织，当为磐石',
    tags: ['计算机', '球队与部署'],
    accent: '#c1d2e8',
    github: 'https://github.com/Smile-hyc',
    portrait: 'assets/images/members/han-yuchen.png',
    portraitFocus: 0.49,
  },
]);

export function safeGithubUrl(value) {
  if (typeof value !== 'string' || !value) return null;
  try {
    const url = new URL(value);
    if (url.protocol !== 'https:' || url.hostname !== 'github.com' || url.username || url.password || url.port) return null;
    return url.href;
  } catch {
    return null;
  }
}
