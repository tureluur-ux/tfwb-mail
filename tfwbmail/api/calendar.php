<?php
require __DIR__.'/../lib.php';
boot();
require_login();
header('Content-Type: application/json; charset=utf-8');

try{
 if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete'])){
  csrf_check();
  $id=(int)($_POST['id']??0);
  $q=db()->prepare("DELETE FROM calendar_events WHERE id=?");
  $q->execute([$id]);
 }elseif($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $title=trim((string)($_POST['title']??''));
  $starts=trim((string)($_POST['starts_at']??''));
  if($title===''||$starts==='')throw new RuntimeException('Vul titel en starttijd in.');
  $q=db()->prepare("INSERT INTO calendar_events(title,location,starts_at,ends_at,notes) VALUES(?,?,?,?,?)");
  $q->execute([$title,trim((string)($_POST['location']??'')),$starts,trim((string)($_POST['ends_at']??'')) ?: null,trim((string)($_POST['notes']??''))]);
 }
 echo json_encode(['ok'=>true]);
}catch(Throwable $e){
 http_response_code(400);
 echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
