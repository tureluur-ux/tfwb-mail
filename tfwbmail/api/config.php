<?php
require __DIR__.'/../lib.php';
boot();
header('Content-Type: application/json; charset=utf-8');

$domain=strtolower(trim((string)($_GET['domain']??'')));
$domain=preg_replace('/[^a-z0-9.-]/','',$domain);

if(!filter_var('test@'.$domain,FILTER_VALIDATE_EMAIL)||strlen($domain)>253){
 http_response_code(400);
 echo json_encode(['ok'=>false,'error'=>'Ongeldig domein.']);
 exit;
}

$known=[
 'xs4all.nl'=>['imap'=>'imap.xs4all.nl','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.xs4all.nl','smtp_port'=>465,'smtp_security'=>'ssl'],
 'zeelandnet.nl'=>['imap'=>'imap.xs4all.nl','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.xs4all.nl','smtp_port'=>465,'smtp_security'=>'ssl'],
 'casema.nl'=>['imap'=>'imap.xs4all.nl','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.xs4all.nl','smtp_port'=>465,'smtp_security'=>'ssl'],
 'kpnmail.nl'=>['imap'=>'imap.kpnmail.nl','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.kpnmail.nl','smtp_port'=>465,'smtp_security'=>'ssl'],
 'planet.nl'=>['imap'=>'imap.kpnmail.nl','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.kpnmail.nl','smtp_port'=>465,'smtp_security'=>'ssl'],
 'hetnet.nl'=>['imap'=>'imap.kpnmail.nl','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.kpnmail.nl','smtp_port'=>465,'smtp_security'=>'ssl'],
 'outlook.com'=>['imap'=>'outlook.office365.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.office365.com','smtp_port'=>587,'smtp_security'=>'tls'],
 'hotmail.com'=>['imap'=>'outlook.office365.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.office365.com','smtp_port'=>587,'smtp_security'=>'tls'],
 'live.com'=>['imap'=>'outlook.office365.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.office365.com','smtp_port'=>587,'smtp_security'=>'tls'],
 'live.nl'=>['imap'=>'outlook.office365.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.office365.com','smtp_port'=>587,'smtp_security'=>'tls'],
 'office365.com'=>['imap'=>'outlook.office365.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.office365.com','smtp_port'=>587,'smtp_security'=>'tls'],
 'gmail.com'=>['imap'=>'imap.gmail.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.gmail.com','smtp_port'=>465,'smtp_security'=>'ssl'],
 'googlemail.com'=>['imap'=>'imap.gmail.com','imap_port'=>993,'imap_security'=>'ssl','smtp'=>'smtp.gmail.com','smtp_port'=>465,'smtp_security'=>'ssl']
];

if(isset($known[$domain])){
 echo json_encode(['ok'=>true,'source'=>'local-preset','config'=>$known[$domain]]);
 exit;
}

echo json_encode(['ok'=>false,'error'=>'Geen lokale preset gevonden. Kies Handmatige instellingen.']);
