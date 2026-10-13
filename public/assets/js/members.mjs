export const members = Object.freeze([
  {
    id: 'lin-ziyuan',
    name: '林子媛',
    english: 'LIN ZIYUAN',
    shortName: 'ZIYUAN',
    gender: 'female',
    school: '密码与网络空间安全学院',
    role: '团队主页 · 页面与交互',
    task: '搭建团队展示页面，完善成员切换、视觉和交互效果。',
    quote: '把想法写成代码，让协作看得见。',
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
    task: '完善成员信息、内容管理与留言功能，连接前台和后台。',
    quote: '让每一条信息，都有清晰的去处。',
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
    task: '后续负责比赛、赛程与数据库设计；本轮先参与团队主页讨论。',
    quote: '从一张数据表开始，把整个项目连起来。',
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
    task: '后续完善球队数据与部署验证；本轮先参与团队主页讨论。',
    quote: '不仅让它跑起来，也让别人顺利跑起来。',
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
