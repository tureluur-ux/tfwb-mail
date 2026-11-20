<?php
require __DIR__.'/../lib.php';boot();require_login();header('Content-Type: application/json; charset=utf-8');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Ongeldige aanvraag.');
 csrf_check();
 if(isset($_POST['delete'])){
  $id=(int)($_POST['id']??0);
  db()->prepare("DELETE FROM contacts WHERE id=?")->execute([$id]);
  echo json_encode(['ok'=>true]);exit;
 }
 $name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));
 if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Vul een naam en geldig e-mailadres in.');
 $q=db()->prepare("INSERT INTO contacts(name,email,phone,company,notes) VALUES(?,?,?,?,?)");
 $q->execute([$name,$email,trim((string)($_POST['phone']??'')),trim((string)($_POST['company']??'')),trim((string)($_POST['notes']??''))]);
 echo json_encode(['ok'=>true]);
}catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
