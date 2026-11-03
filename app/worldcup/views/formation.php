<?php
/** FOURWARD coursework | Author: 韩羽宸 | Responsive green pitch with actual starters and nation-colored shirt markers. */
declare(strict_types=1);
$formation=$match['formation'];
// Nation-inspired colors, not a claim about the kits worn in a particular fixture.
$nationPaint=[
  'ALG'=>['#ffffff','#23804c'],'ARG'=>['#75bce6','#ffffff'],'AUS'=>['#f8ce35','#20754c'],'AUT'=>['#dc3039','#ffffff'],
  'BEL'=>['#20252b','#f2c63c','#d4414c'],'BIH'=>['#1d53a7','#f0d03b'],'BRA'=>['#1c914d','#f5d441'],'CPV'=>['#1753a0','#ffffff','#d34b50'],
  'CAN'=>['#e63c45','#ffffff','#e63c45'],'COL'=>['#eedb44','#2456af','#d74950'],'COD'=>['#48a7dc','#eedb43','#d93f4d'],'CIV'=>['#ef8f3c','#ffffff','#36965d'],
  'CRO'=>['#df4549','#ffffff','#446db3'],'CUW'=>['#244ea3','#f2da50'],'CZE'=>['#ffffff','#db424b','#315ba4'],'ECU'=>['#f1d544','#2c55a7','#d8484e'],
  'EGY'=>['#db414b','#ffffff','#20252b'],'ENG'=>['#ffffff','#cc3847','#ffffff'],'FRA'=>['#2852a0','#ffffff','#d4434d'],'GER'=>['#25282c','#ca3946','#edc542'],
  'GHA'=>['#ce3d49','#edce43','#25845c'],'HAI'=>['#3059aa','#d0424d'],'IRN'=>['#2b8b61','#ffffff','#d54850'],'IRQ'=>['#d34850','#ffffff','#25282c'],
  'JPN'=>['#ffffff','#d64352','#ffffff'],'JOR'=>['#272c32','#ffffff','#2e8d62'],'KOR'=>['#ffffff','#ce414c','#315ea6'],'MEX'=>['#27875d','#ffffff','#d3464e'],
  'MAR'=>['#ce3d49','#267b56'],'NED'=>['#d6434c','#ffffff','#3762aa'],'NZL'=>['#244d95','#d44852'],'NOR'=>['#cf414d','#ffffff','#315a9b'],
  'PAN'=>['#d5404a','#ffffff','#315ca5'],'PAR'=>['#d94850','#ffffff','#345da0'],'POR'=>['#2c8759','#c73e46'],'QAT'=>['#ffffff','#8c304d'],
  'KSA'=>['#238559','#ffffff'],'SCO'=>['#2e63ae','#ffffff'],'SEN'=>['#278b5e','#edce43','#d94650'],'RSA'=>['#288c5d','#eed14b','#315b9f'],
  'ESP'=>['#d23c49','#edcc43','#d23c49'],'SWE'=>['#3b75b8','#f1d047'],'SUI'=>['#d43c48','#ffffff'],'TUN'=>['#d8414e','#ffffff'],
  'TUR'=>['#d83b47','#ffffff'],'URU'=>['#ffffff','#72b4df'],'USA'=>['#d84a55','#ffffff','#355da0'],'UZB'=>['#56b9d4','#ffffff','#43a168'],
];
?>
<section class="wc-panel wc-formation-panel" id="formation" aria-labelledby="formation-title">
  <div class="wc-section-heading"><div><p class="wc-eyebrow">STARTING XI · ON THE PITCH</p><h2 id="formation-title">首发阵容图</h2></div><span class="wc-muted">点击球员查看个人数据 ↗</span></div>
  <?php if(!$formation): ?><div class="wc-empty"><p>本场暂无已核实的阵型与站位，球员名单见下方。</p></div><?php else: ?>
  <div class="wc-formation-teams"><?php foreach(['home','away'] as $side): ?><a href="<?= e($url('teams',['id'=>$match[$side.'_team_id']])) ?>"><?= $flag($match[$side.'_code'],$match[$side.'_name']) ?><strong><?= e($match[$side.'_name']) ?></strong><b><?= e($formation['sides'][$side]['formation']) ?></b><small><?= $side==='home'?'主队':'客队' ?></small></a><?php endforeach; ?></div>
  <div class="wc-pitch" aria-label="<?= e($match['home_name'].'对'.$match['away_name'].'首发阵容示意图') ?>">
    <svg class="wc-pitch-lines" viewBox="0 0 1000 640" preserveAspectRatio="none" aria-hidden="true"><rect x="22" y="20" width="956" height="600" rx="2"/><path d="M500 20V620 M22 188H174V452H22 M22 258H82V382H22 M978 188H826V452H978 M978 258H918V382H978"/><ellipse cx="500" cy="320" rx="85" ry="86"/><ellipse cx="500" cy="320" rx="2" ry="2"/><path d="M174 262Q228 320 174 378 M826 262Q772 320 826 378"/><circle cx="124" cy="320" r="2"/><circle cx="876" cy="320" r="2"/></svg>
    <svg class="wc-pitch-lines wc-pitch-lines--vertical" viewBox="0 0 640 1000" preserveAspectRatio="none" aria-hidden="true"><g transform="translate(640 0) rotate(90)"><rect x="22" y="20" width="956" height="600" rx="2"/><path d="M500 20V620 M22 188H174V452H22 M22 258H82V382H22 M978 188H826V452H978 M978 258H918V382H978"/><ellipse cx="500" cy="320" rx="85" ry="86"/><circle cx="500" cy="320" r="2"/><path d="M174 262Q228 320 174 378 M826 262Q772 320 826 378"/><circle cx="124" cy="320" r="2"/><circle cx="876" cy="320" r="2"/></g></svg>
    <?php foreach(['home','away'] as $side): $colors=$nationPaint[$match[$side.'_code']]??['#546c98','#d2deec'];$bands=[];foreach($colors as $i=>$color)$bands[]=$color.' '.round(100*$i/count($colors),2).'% '.round(100*($i+1)/count($colors),2).'%';$paint='linear-gradient(90deg,'.implode(',',$bands).')'; ?>
    <div class="wc-pitch-side wc-pitch-side--<?= e($side) ?>" aria-label="<?= e($match[$side.'_name'].'首发') ?>">
      <?php foreach($formation['sides'][$side]['starters'] as $p):
        $forward=4+0.46*$p['y'];$cross=5+0.9*$p['x'];$left=$side==='home'?$forward:100-$forward;$top=$side==='home'?$cross:100-$cross;
        $name=$p['display_name'];
      ?><a class="wc-pitch-player<?= $p['position']==='GK'?' wc-pitch-goalkeeper':'' ?>" href="<?= e($url('player',['id'=>$p['player_id'],'match_id'=>$match['id']])) ?>#single-performance" style="--pitch-x:<?= e($left) ?>%;--pitch-y:<?= e($top) ?>%;--pitch-mobile-x:<?= e($top) ?>%;--pitch-mobile-y:<?= e($left) ?>%;--nation-paint:<?= e($paint) ?>" aria-label="<?= e($match[$side.'_name'].'，'.$p['shirt_number'].'号 '.$p['name'].'，'.($positions[$p['position']]??$p['position'])) ?>" title="<?= e($p['name']) ?>">
        <span class="wc-pitch-shirt"><b><?= e($p['shirt_number']) ?></b><?php if($p['captain']): ?><i class="wc-pitch-captain" title="队长">C</i><?php endif; ?></span>
        <span class="wc-pitch-player-name"><?= e($name) ?></span>
        <?php if($p['markers']): ?><span class="wc-pitch-events"><?php foreach($p['markers'] as $event): ?><span class="wc-pitch-event wc-pitch-event--<?= e($event['event_type']) ?>" title="<?= e($event['minute_label'].' '.$eventLabels[$event['event_type']]) ?>"><?= e(match($event['event_type']){'yellow_card'=>'▰','red_card'=>'▰','goal'=>'⚽','own_goal'=>'OG',default=>'↙ '.$event['minute_label']}) ?></span><?php endforeach; ?></span><?php endif; ?>
      </a><?php endforeach; ?>
    </div><?php endforeach; ?>
    <span class="wc-pitch-watermark" aria-hidden="true">FOURWARD / 2026</span>
  </div>
  <div class="wc-pitch-legend"><span><i class="wc-legend-dot"></i> 门将</span><span><b class="wc-legend-captain">C</b> 队长</span><span>↙ 换下</span><span>圆标采用国家队配色，非当场球衣；站位为首发阵型示意。</span></div>
  <?php endif; ?>
</section>
