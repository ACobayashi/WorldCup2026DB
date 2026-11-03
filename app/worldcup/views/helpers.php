<?php
/** FOURWARD coursework | Author: 韩羽宸 | Escaped, local-only team flag and display helpers. */
declare(strict_types=1);
$flag = static function (?string $code, string $name = ''): string {
  $known = ['ALG','ARG','AUS','AUT','BEL','BIH','BRA','CPV','CAN','COL','COD','CIV','CRO','CUW','CZE','ECU','EGY','ENG','FRA','GER','GHA','HAI','IRN','IRQ','JPN','JOR','KOR','MEX','MAR','NED','NZL','NOR','PAN','PAR','POR','QAT','KSA','SCO','SEN','RSA','ESP','SWE','SUI','TUN','TUR','URU','USA','UZB'];
  if (!in_array($code,$known,true)) return '<span class="wc-flag wc-flag-placeholder" aria-hidden="true">?</span>';
  return '<img class="wc-flag" src="../assets/images/flags/'.e($code).'.svg" width="40" height="30" alt="'.e(($name ?: $code).'国旗').'" loading="lazy">';
};
$number = static fn(mixed $value, string $suffix=''): string => $value===null ? '—' : rtrim(rtrim(number_format((float)$value,2,'.',''),'0'),'.').$suffix;
$positions = ['GK'=>'门将','DF'=>'后卫','MF'=>'中场','FW'=>'前锋'];
$eventLabels = ['goal'=>'进球','own_goal'=>'乌龙球','yellow_card'=>'黄牌','red_card'=>'红牌','subbed_on'=>'换上','subbed_off'=>'换下'];
$eventIcons = ['goal'=>'⚽','own_goal'=>'↩','yellow_card'=>'▰','red_card'=>'▰','subbed_on'=>'↗','subbed_off'=>'↙'];
