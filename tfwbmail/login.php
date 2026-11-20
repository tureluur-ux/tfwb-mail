<?php
require __DIR__.'/lib.php';
boot();
$error='';
$savedAccounts=accounts();
$mode=$_POST['mode']??(($savedAccounts && empty($_GET['setup']))?'login':'setup');

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  try{
    if($mode==='login'){
      $id=(int)($_POST['account_id']??0);
      $password=(string)($_POST['password']??'');
      if(!$id||$password==='')throw new RuntimeException('Kies een account en vul je wachtwoord in.');
      $account=login_account($id,$password);
      $_SESSION['account_id']=$account['id'];
      if(!empty($_POST['remember'])){
        setcookie(session_name(),session_id(),[
          'expires'=>time()+60*60*24*30,
          'path'=>'/',
          'httponly'=>true,
          'samesite'=>'Lax'
        ]);
      }
      header('Location: index.php');exit;
    }
    $id=add_account($_POST);
    $_SESSION['account_id']=$id;
    if(!empty($_POST['remember'])){
      setcookie(session_name(),session_id(),[
        'expires'=>time()+60*60*24*30,
        'path'=>'/',
        'httponly'=>true,
        'samesite'=>'Lax'
      ]);
    }
    header('Location: index.php');exit;
  }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $mode==='login'?'Inloggen':'tfwbmail instellen' ?></title>
  <link rel="icon" href="assets/logo.svg">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<form class="login-card" method="post">
  <div class="brand centered">
    <span class="brandmark">TB</span>
    <img class="brandmark login-logo" src="assets/logo.svg" alt=""><span>tfwbmail<small>Jouw lokale postomgeving</small></span>
  </div>
  <?php if($error):?><div class="error"><?=h($error)?></div><?php endif;?>
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  <input type="hidden" name="mode" value="<?=h($mode)?>">

  <?php if($mode==='login'): ?>
    <h1>Welkom terug</h1>
    <p>Kies je account en log in. Je servergegevens zijn al opgeslagen.</p>
    <label>Account
      <select name="account_id" required>
        <?php foreach($savedAccounts as $saved): ?>
          <option value="<?= (int)$saved['id'] ?>"><?=h(($saved['name']?$saved['name'].' — ':'').$saved['email'])?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Wachtwoord<input name="password" type="password" required autocomplete="current-password" autofocus></label>
    <label class="check"><input type="checkbox" name="remember" checked> Ingelogd blijven</label>
    <button class="primary full">Inloggen</button>
    <a class="setup-link" href="?setup=1">Nog een account toevoegen</a>
  <?php else: ?>
    <h1>Account instellen</h1>
    <p>Dit is eenmalig. Kies je provider; tfwbmail vult de juiste instellingen in.</p>
    <label>Provider
      <select id="providerPreset">
        <option value="auto">Automatisch op basis van e-mailadres</option>
        <option value="stalwart">Stalwart / Zelf gehost</option>
        <option value="xs4all">XS4ALL</option>
        <option value="kpn">KPN</option>
        <option value="outlook">Microsoft Outlook / Office 365</option>
        <option value="gmail">Google Gmail</option>
        <option value="custom">Handmatige instellingen</option>
      </select>
    </label>
    <label>Naam<input name="name" placeholder="Je naam" autocomplete="name"></label>
    <label>E-mailadres<input id="email" name="email" type="email" required placeholder="jij@xs4all.nl" autocomplete="email"></label>
    <label>Wachtwoord<input name="password" type="password" required autocomplete="new-password"></label>
    <div class="two">
      <label>IMAP-server<input id="imapHost" name="imap_host" required value="stalwart"></label>
      <label>IMAP-poort<input id="imapPort" name="imap_port" type="number" value="993" required></label>
    </div>
    <div class="two">
      <label>SMTP-server<input id="smtpHost" name="smtp_host" required value="stalwart"></label>
      <label>SMTP-poort<input id="smtpPort" name="smtp_port" type="number" value="465" required></label>
    </div>
    <div class="two">
      <label>IMAP-beveiliging<select id="imapSecurity" name="imap_security"><option value="ssl">SSL/TLS</option><option value="tls">STARTTLS</option></select></label>
      <label>SMTP-beveiliging<select id="smtpSecurity" name="smtp_security"><option value="ssl">SSL/TLS</option><option value="tls">STARTTLS</option></select></label>
    </div>
    <label class="check"><input type="checkbox" name="remember" checked> Account op deze computer onthouden</label>
    <button class="primary full">Account opslaan en openen</button>
    <small class="muted">Het wachtwoord wordt versleuteld opgeslagen in de lokale tfwbmail-database.</small>
    <button type="submit" class="danger full" formaction="reset.php" onclick="return confirm('Alle lokale tfwbmail accounts en instellingen wissen?')">Lokale gegevens resetten</button>
  <?php endif;?>
</form>
<script>
const presets={
  stalwart:{imap:'stalwart',imapPort:'993',smtp:'stalwart',smtpPort:'465',imapSecurity:'ssl',smtpSecurity:'ssl'},
  xs4all:{imap:'imap.xs4all.nl',imapPort:'993',smtp:'smtp.xs4all.nl',smtpPort:'465',imapSecurity:'ssl',smtpSecurity:'ssl'},
  kpn:{imap:'imap.kpnmail.nl',imapPort:'993',smtp:'smtp.kpnmail.nl',smtpPort:'465',imapSecurity:'ssl',smtpSecurity:'ssl'},
  outlook:{imap:'outlook.office365.com',imapPort:'993',smtp:'smtp.office365.com',smtpPort:'587',imapSecurity:'ssl',smtpSecurity:'tls'},
  gmail:{imap:'imap.gmail.com',imapPort:'993',smtp:'smtp.gmail.com',smtpPort:'465',imapSecurity:'ssl',smtpSecurity:'ssl'}
};
const domainPresets={
  'xs4all.nl':'xs4all',
  'zeelandnet.nl':'xs4all',
  'casema.nl':'xs4all',
  'kpnmail.nl':'kpn',
  'planet.nl':'kpn',
  'hetnet.nl':'kpn',
  'outlook.com':'outlook',
  'hotmail.com':'outlook',
  'live.com':'outlook',
  'live.nl':'outlook',
  'office365.com':'outlook',
  'gmail.com':'gmail',
  'googlemail.com':'gmail'
};
const preset=document.querySelector('#providerPreset');
const email=document.querySelector('#email');
let detectTimer=null;
function applyPreset(name){
  const p=presets[name];
  if(!p)return;
  document.querySelector('#imapHost').value=p.imap;
  document.querySelector('#imapPort').value=p.imapPort;
  document.querySelector('#smtpHost').value=p.smtp;
  document.querySelector('#smtpPort').value=p.smtpPort;
  document.querySelector('#imapSecurity').value=p.imapSecurity;
  document.querySelector('#smtpSecurity').value=p.smtpSecurity;
}
if(preset){
  preset.addEventListener('change',()=>{
    if(preset.value==='auto')detectDomain();
    else if(preset.value==='custom'){}
    else applyPreset(preset.value);
  });
}
function detectDomain(){
  const value=(email?.value||'').trim().toLowerCase();
  const at=value.lastIndexOf('@');
  if(at<0)return;
  const domain=value.slice(at+1);
  const provider=domainPresets[domain];
  if(provider){
    applyPreset(provider);
    return;
  }
  clearTimeout(detectTimer);
  detectTimer=setTimeout(async()=>{
    try{
      const response=await fetch('api/config.php?domain='+encodeURIComponent(domain));
      const data=await response.json();
      if(!response.ok||!data.ok)return;
      const c=data.config;
      document.querySelector('#imapHost').value=c.imap;
      document.querySelector('#imapPort').value=c.imap_port;
      document.querySelector('#smtpHost').value=c.smtp;
      document.querySelector('#smtpPort').value=c.smtp_port;
      document.querySelector('#imapSecurity').value=c.imap_security;
      document.querySelector('#smtpSecurity').value=c.smtp_security;
    }catch(_error){}
  },700);
}
if(email){
  email.addEventListener('input',()=>{
    if(preset.value==='auto')detectDomain();
  });
  email.addEventListener('change',()=>{
    if(preset.value==='auto')detectDomain();
  });
}
</script>
</body>
</html>
