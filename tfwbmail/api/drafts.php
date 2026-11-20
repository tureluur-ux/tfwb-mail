<?php
require __DIR__.'/../lib.php';
boot();
require_login();
header('Content-Type: application/json; charset=utf-8');

try{
 $account=account();
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $q=db()->prepare("SELECT * FROM drafts WHERE account_id=? ORDER BY updated_at DESC");
  $q->execute([$account['id']]);
  echo json_encode(['ok'=>true,'drafts'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
  exit;
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Ongeldige aanvraag.');
 csrf_check();
 $id=(int)($_POST['id']??0);
 if($_SERVER['REQUEST_METHOD']==='DELETE'||(!empty($_POST['delete']))){
  $delete=db()->prepare("DELETE FROM drafts WHERE id=? AND account_id=?");
  $delete->execute([$id,$account['id']]);
  echo json_encode(['ok'=>true]);
  exit;
 }
 $to=trim((string)($_POST['to']??''));
 $subject=trim((string)($_POST['subject']??''));
 $body=(string)($_POST['body']??'');
 if($id){
  $q=db()->prepare("UPDATE drafts SET to_email=?,subject=?,body=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND account_id=?");
  $q->execute([$to,$subject,$body,$id,$account['id']]);
 }else{
  $q=db()->prepare("INSERT INTO drafts(account_id,to_email,subject,body) VALUES(?,?,?,?)");
  $q->execute([$account['id'],$to,$subject,$body]);
  $id=(int)db()->lastInsertId();
 }
 echo json_encode(['ok'=>true,'id'=>$id]);
}catch(Throwable $e){
 http_response_code(400);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
