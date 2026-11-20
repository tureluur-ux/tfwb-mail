<?php
declare(strict_types=1);
function boot(): void {
 if(session_status()!==PHP_SESSION_ACTIVE){session_name('tbmail');session_start();}
 $dir=__DIR__.'/data'; if(!is_dir($dir))mkdir($dir,0770,true);
 $ht=$dir.'/.htaccess'; if(!file_exists($ht))file_put_contents($ht,"Require all denied\n");
 $db=$dir.'/tbmail.sqlite';
 if(!file_exists($db)){ $pdo=new PDO('sqlite:'.$db);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec("CREATE TABLE accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT UNIQUE,password TEXT,imap_host TEXT,imap_port INTEGER,smtp_host TEXT,smtp_port INTEGER,imap_security TEXT,smtp_security TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");}
 $pdo=new PDO('sqlite:'.$db);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,location TEXT,starts_at TEXT NOT NULL,ends_at TEXT,notes TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
 $pdo->exec("CREATE TABLE IF NOT EXISTS contacts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NOT NULL,phone TEXT,company TEXT,notes TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
 $pdo->exec("CREATE TABLE IF NOT EXISTS drafts(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER NOT NULL,to_email TEXT,subject TEXT,body TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
 $pdo->exec("CREATE TABLE IF NOT EXISTS labels(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER NOT NULL,name TEXT NOT NULL,color TEXT DEFAULT '#315bea',UNIQUE(account_id,name))");
 $pdo->exec("CREATE TABLE IF NOT EXISTS message_cache(id INTEGER PRIMARY KEY AUTOINCREMENT,account_id INTEGER NOT NULL,folder TEXT NOT NULL,uid INTEGER NOT NULL,from_email TEXT,subject TEXT,body TEXT,date TEXT,seen INTEGER DEFAULT 0,UNIQUE(account_id,folder,uid))");
}
function db():PDO{$p=new PDO('sqlite:'.__DIR__.'/data/tbmail.sqlite');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);return $p;}
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function csrf_check():void{if(!hash_equals(csrf_token(),(string)($_POST['csrf']??''))){http_response_code(403);exit('Ongeldige aanvraag.');}}
function require_login():void{if(empty($_SESSION['account_id'])){header('Location: login.php');exit;}}
function account():array{require_login();$s=db()->prepare('SELECT * FROM accounts WHERE id=?');$s->execute([$_SESSION['account_id']]);$a=$s->fetch(PDO::FETCH_ASSOC);if(!$a){session_destroy();header('Location: login.php');exit;}return $a;}
function accounts():array{$s=db()->query('SELECT id,name,email,imap_host,imap_port,smtp_host,smtp_port,imap_security,smtp_security FROM accounts ORDER BY name,email');return $s->fetchAll(PDO::FETCH_ASSOC);}
function login_account(int $id,string $password):array{
 $q=db()->prepare('SELECT * FROM accounts WHERE id=?');$q->execute([$id]);$a=$q->fetch(PDO::FETCH_ASSOC);
 if(!$a)throw new RuntimeException('Dit account bestaat niet meer.');
 try{$stored=decrypt_secret($a['password']);}catch(Throwable $e){throw new RuntimeException('Het opgeslagen wachtwoord kon niet worden gelezen. Stel het account opnieuw in.');}
 if(!hash_equals($stored,$password))throw new RuntimeException('Het wachtwoord is onjuist.');
 return $a;
}
function key_material():string{$f=__DIR__.'/data/.key';if(!file_exists($f))file_put_contents($f,random_bytes(32),LOCK_EX);@chmod($f,0600);return file_get_contents($f);}
function encrypt_secret(string $s):string{$iv=random_bytes(12);$tag='';$v=openssl_encrypt($s,'aes-256-gcm',hash('sha256',key_material(),true),OPENSSL_RAW_DATA,$iv,$tag);return base64_encode($iv.$tag.$v);}
function decrypt_secret(string $s):string{$d=base64_decode($s,true);if(!$d||strlen($d)<29)throw new RuntimeException('Wachtwoord kan niet worden ontsleuteld.');$iv=substr($d,0,12);$tag=substr($d,12,16);$v=openssl_decrypt(substr($d,28),'aes-256-gcm',hash('sha256',key_material(),true),OPENSSL_RAW_DATA,$iv,$tag);if($v===false)throw new RuntimeException('Wachtwoord ontsleutelen mislukt.');return $v;}
function add_account(array $v):int{
 foreach(['email','password','imap_host','imap_port','smtp_host','smtp_port'] as $k)if(trim((string)($v[$k]??''))==='')throw new RuntimeException('Vul alle verplichte velden in.');
 if(!filter_var($v['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Vul een geldig e-mailadres in.');
 test_account_connection($v);
 $q=db()->prepare('INSERT INTO accounts(name,email,password,imap_host,imap_port,smtp_host,smtp_port,imap_security,smtp_security) VALUES(?,?,?,?,?,?,?,?,?)');
 try{$q->execute([trim($v['name']??''),trim($v['email']),encrypt_secret($v['password']),trim($v['imap_host']),(int)$v['imap_port'],trim($v['smtp_host']),(int)$v['smtp_port'],$v['imap_security']==='tls'?'tls':'ssl',$v['smtp_security']==='tls'?'tls':'ssl']);}catch(Throwable $e){throw new RuntimeException('Dit e-mailadres bestaat al, of de database kon niet worden bijgewerkt.');}
 return (int)db()->lastInsertId();
}
function test_account_connection(array $v):void{
 if(!function_exists('imap_open'))throw new RuntimeException('De PHP IMAP-extensie staat niet aan.');
 $host=trim($v['imap_host']);
 $port=(int)$v['imap_port'];
 if(!preg_match('/^[a-z0-9.-]+$/i',$host))throw new RuntimeException('Ongeldige IMAP-servernaam.');
 $mailbox='{'.$host.':'.$port.'/imap'.($v['imap_security']==='ssl'?'/ssl':'/tls').'/novalidate-cert}INBOX';
 $imap=@imap_open($mailbox,$v['email'],$v['password'],OP_READONLY,1);
 if(!$imap){
  $error=imap_last_error();
  imap_errors();
  throw new RuntimeException('Verbinding met IMAP mislukt: '.($error?:'controleer e-mailadres, wachtwoord en beveiligingsinstellingen.'));
 }
 imap_close($imap);
}
function imap_connection(array $a){
 if(!function_exists('imap_open'))throw new RuntimeException('De PHP IMAP-extensie staat niet aan. Activeer extension=imap in php.ini en herstart Apache.');
 $flags=($a['imap_security']==='ssl'?'/imap/ssl':'/imap/tls').'/novalidate-cert';
 $mailbox='{'.$a['imap_host'].':'.$a['imap_port'].'/imap'.($a['imap_security']==='ssl'?'/ssl':'/tls').'/novalidate-cert}';
 $m=@imap_open($mailbox.'INBOX',$a['email'],decrypt_secret($a['password']));
 if(!$m)throw new RuntimeException('IMAP-inloggen mislukt: '.(imap_last_error()?:'Controleer server, poort en wachtwoord.'));
 return $m;
}
function mime_decode(string $s):string{$x=imap_mime_header_decode($s);$o='';foreach($x as $p)$o.=iconv($p->charset==='default'?'UTF-8':$p->charset,'UTF-8//IGNORE',$p->text);return $o?:$s;}
function sanitize_html(string $html):string{
 $html=preg_replace('/<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>.*?<\/\1>/is','',$html)??'';
 $html=preg_replace('/<(script|style|iframe|object|embed|form|link|meta)\b[^>]*\/?>/is','',$html)??'';
 $html=preg_replace('/\s(?:on[a-z]+|srcdoc)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^>\s]+)/is','',$html)??'';
 $html=preg_replace('/(?:javascript|vbscript)\s*:/is','',$html)??'';
 return $html;
}
function message_body($imap,int $n):string{$st=imap_fetchstructure($imap,$n);$body='';if(!$st)return '';if(!empty($st->parts)){foreach($st->parts as $i=>$p){$sub=strtolower($p->subtype??'');if(($p->type??-1)===0&&in_array($sub,['plain','html'],true)){ $body=imap_fetchbody($imap,$n,(string)($i+1));$enc=$p->encoding??0;if($enc===3)$body=base64_decode($body);elseif($enc===4)$body=quoted_printable_decode($body);if($sub==='html')return $body;}}}else{$body=imap_body($imap,$n);if(($st->encoding??0)===3)$body=base64_decode($body);elseif(($st->encoding??0)===4)$body=quoted_printable_decode($body);}return $body;}
function smtp_send(array $a,string $to,string $subject,string $body,?array $file):void{
 $host=$a['smtp_host'];$port=(int)$a['smtp_port'];$ssl=$a['smtp_security']==='ssl';$remote=($ssl?'ssl://':'').$host.':'.$port;$fp=@stream_socket_client($remote,$errno,$errstr,20,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]));if(!$fp)throw new RuntimeException("SMTP-verbinding mislukt: $errstr ($errno)");stream_set_timeout($fp,20);
 $read=function()use($fp){$r='';while(($l=fgets($fp,515))!==false){$r.=$l;if(strlen($l)<4||$l[3]===' ')break;}if(!preg_match('/^[23]/',$r))throw new RuntimeException('SMTP-server: '.trim($r));return $r;};
 $write=function($s)use($fp){fwrite($fp,$s."\r\n");};
 try{$read();$write('EHLO tbmail.local');$read();if(!$ssl){$write('STARTTLS');$read();if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('STARTTLS kon niet worden gestart.');$write('EHLO tbmail.local');$read();}
 $write('AUTH LOGIN');$read();$write(base64_encode($a['email']));$read();$write(base64_encode(decrypt_secret($a['password'])));$read();$write('MAIL FROM:<'.$a['email'].'>');$read();$write('RCPT TO:<'.$to.'>');$read();$write('DATA');$read();
 $boundary='tb_'.bin2hex(random_bytes(8));$headers="From: ".mb_encode_mimeheader($a['name']?:$a['email'])." <".$a['email'].">\r\nTo: ".$to."\r\nSubject: ".mb_encode_mimeheader($subject,'UTF-8')."\r\nMIME-Version: 1.0\r\n";
 if($file&&is_uploaded_file($file['tmp_name'])&&$file['error']===UPLOAD_ERR_OK){$headers.="Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body))."\r\n--$boundary\r\nContent-Type: application/octet-stream; name=\"".addslashes(basename($file['name']))."\"\r\nContent-Disposition: attachment; filename=\"".addslashes(basename($file['name']))."\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode(file_get_contents($file['tmp_name'])))."\r\n--$boundary--";}
 else{$headers.="Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body));}
 $write(str_replace(["\r\n."],["\r\n.."],$headers)."\r\n.");$read();$write('QUIT');fclose($fp);
 }catch(Throwable $e){fclose($fp);throw $e;}
}
