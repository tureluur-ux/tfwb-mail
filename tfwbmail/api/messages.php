<?php
require __DIR__.'/../lib.php';boot();require_login();header('Content-Type: application/json; charset=utf-8');
try{$a=account();$folder=preg_replace('/[^A-Za-z0-9 _-]/','',$_GET['folder']??'INBOX');$imap=imap_connection($a);$boxes=imap_getmailboxes($imap,'{'.$a['imap_host'].'}', '*')?:[];$target='INBOX';foreach($boxes as $b){$name=substr($b->name,strrpos($b->name,'}')+1);if(strcasecmp($name,$folder)===0){$target=$name;break;}}
 if($folder==='Drafts'){
  $drafts=db()->prepare("SELECT id,to_email AS from_email,subject,body,updated_at AS date FROM drafts WHERE account_id=? ORDER BY updated_at DESC");
  $drafts->execute([$a['id']]);
  echo json_encode(['ok'=>true,'messages'=>$drafts->fetchAll(PDO::FETCH_ASSOC),'count'=>$drafts->rowCount()]);
  exit;
 }
 if($folder!=='INBOX'&&$target==='INBOX'){$target=$folder;}
 if(!@imap_reopen($imap,'{'.$a['imap_host'].':'.$a['imap_port'].'/imap'.($a['imap_security']==='ssl'?'/ssl':'/tls').'/novalidate-cert}'.$target))throw new RuntimeException('Map niet gevonden: '.$folder);
 $ids=imap_sort($imap,SORTDATE,true,SE_UID)?:[];$out=[];$query=trim($_GET['q']??'');foreach(array_slice($ids,0,150) as $uid){$n=imap_msgno($imap,$uid);$ov=imap_fetch_overview($imap,(string)$n,0)[0]??null;if(!$ov)continue;$from=mime_decode($ov->from??'Onbekend');$subject=mime_decode($ov->subject??'(Geen onderwerp)');if($query!==''&&stripos($from.' '.$subject,$query)===false)continue;$out[]=['uid'=>(int)$uid,'from'=>$from,'subject'=>$subject,'date'=>$ov->date??'','seen'=>!empty($ov->seen)];}
 foreach($out as $item){
  $cache=db()->prepare("INSERT INTO message_cache(account_id,folder,uid,from_email,subject,date,seen) VALUES(?,?,?,?,?,?,?) ON CONFLICT(account_id,folder,uid) DO UPDATE SET from_email=excluded.from_email,subject=excluded.subject,date=excluded.date,seen=excluded.seen");
  $cache->execute([$a['id'],$target,(int)$item['uid'],$item['from'],$item['subject'],$item['date'],$item['seen']?1:0]);
 }
 imap_close($imap);echo json_encode(['ok'=>true,'messages'=>$out,'count'=>count($out)]);
}catch(Throwable $e){
 $cache=db()->prepare("SELECT uid,from_email AS 'from',subject,date,seen FROM message_cache WHERE account_id=? AND folder=? ORDER BY date DESC LIMIT 150");
 $accountId=isset($a)&&isset($a['id'])?(int)$a['id']:0;
 $cache->execute([$accountId,$folder]);
 $cached=$cache->fetchAll(PDO::FETCH_ASSOC);
 if($cached){echo json_encode(['ok'=>true,'messages'=>$cached,'count'=>count($cached),'offline'=>true]);exit;}
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}