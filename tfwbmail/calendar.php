<?php
require __DIR__.'/lib.php';boot();require_login();
$month=$_GET['month']??date('Y-m');
$start=$month.'-01';
$timestamp=strtotime($start)?:time();
$days=date('t',$timestamp);
$offset=(int)date('N',$timestamp)-1;
$events=db()->prepare("SELECT * FROM calendar_events WHERE starts_at >= ? AND starts_at < ? ORDER BY starts_at");
$events->execute([$start,date('Y-m-d',strtotime('+1 month',$timestamp))]);
$items=$events->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf" content="<?=h(csrf_token())?>"><title>Agenda - tfwbmail</title><link rel="icon" href="assets/logo.svg"><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-page"><main class="calendar-page"><header class="calendar-head"><div><a class="back-link" href="index.php">← Postvak</a><h1>Agenda</h1></div><button class="primary" id="newEvent">＋ Nieuw evenement</button></header>
<div class="calendar-toolbar"><a href="?month=<?=h(date('Y-m',strtotime('-1 month',$timestamp)))?>">←</a><h2><?=h(date('F Y',$timestamp))?></h2><a href="?month=<?=h(date('Y-m',strtotime('+1 month',$timestamp)))?>">→</a></div>
<div class="calendar-grid"><div>Ma</div><div>Di</div><div>Wo</div><div>Do</div><div>Vr</div><div>Za</div><div>Zo</div>
<?php for($i=0;$i<$offset;$i++):?><div class="calendar-day outside"></div><?php endfor;for($day=1;$day<=$days;$day++):$date=$month.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);?><div class="calendar-day<?= $date===date('Y-m-d')?' today':'' ?>"><b><?= $day ?></b><?php foreach($items as $item):if(substr($item['starts_at'],0,10)===$date):?><button class="calendar-event" data-id="<?= (int)$item['id'] ?>" title="<?=h($item['title'])?>"><?=h($item['title'])?></button><?php endif;endforeach;?></div><?php endfor;?></div>
<div class="modal hidden" id="eventModal"><form class="compose-window" id="eventForm"><div class="modal-head"><b>Nieuw evenement</b><button type="button" class="close" id="closeEvent">×</button></div><label>Titel<input name="title" required></label><label>Locatie<input name="location"></label><div class="two"><label>Begint<input name="starts_at" type="datetime-local" required></label><label>Eindigt<input name="ends_at" type="datetime-local"></label></div><label>Notities<textarea name="notes" rows="5"></textarea></label><div class="modal-foot"><span id="eventStatus"></span><button class="primary" type="submit">Opslaan</button></div></form></div></main><script src="assets/calendar.js"></script></body></html>
