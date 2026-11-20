<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
boot();
if (!isset($_SESSION['account_id'])) { header('Location: login.php'); exit; }
$account = account();
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="csrf" content="<?=h(csrf_token())?>"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>tfwbmail</title><link rel="icon" href="assets/logo.svg"><link rel="stylesheet" href="assets/style.css"></head>
<body class="app">
<aside class="sidebar">
  <div class="brand" title="tfwbmail"><img class="brandmark" src="assets/logo.svg" alt="tfwbmail logo"><span>tfwbmail<small>Lokale postomgeving</small></span></div>
  <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-expanded="true" title="Zijbalk inklappen">‹</button>
  <button class="compose" id="composeBtn" title="Nieuwe e-mail"><span class="icon compose-icon" aria-hidden="true">＋</span><span>Nieuwe e-mail</span></button>
  <nav id="folders">
    <button class="folder active" data-folder="INBOX" title="Inbox"><span class="icon">▣</span><span>Inbox</span></button>
    <button class="folder" data-folder="Sent" title="Verzonden"><span class="icon">↗</span><span>Verzonden</span></button>
    <button class="folder" data-folder="Drafts" title="Concepten"><span class="icon">▤</span><span>Concepten</span></button>
    <button class="folder" data-folder="Junk" title="Spam"><span class="icon">⚑</span><span>Spam</span></button>
    <button class="folder" data-folder="Trash" title="Prullenbak"><span class="icon">⌫</span><span>Prullenbak</span></button>
  </nav>
  <nav class="app-nav">
    <a class="folder" href="calendar.php" title="Agenda"><span class="icon">▦</span><span>Agenda</span></a>
    <a class="folder" href="contacts.php" title="Contacten"><span class="icon">◎</span><span>Contacten</span></a>
  </nav>
  <div class="sidebar-bottom"><div class="account-chip"><div class="avatar"><?=h(strtoupper(substr($account['email'],0,1)))?></div><div><b><?=h($account['email'])?></b><small>Verbonden account</small></div></div>
  <a href="settings.php" title="Instellingen"><span class="icon">⚙</span><span>Instellingen</span></a><a href="downloads/tfwbmail.exe" title="tfwbmail downloaden"><span class="icon">⇩</span><span>tfwbmail downloaden</span></a><a href="logout.php" title="Afmelden"><span class="icon">↪</span><span>Afmelden</span></a></div>
</aside>
<main class="main">
<header class="topbar"><div><h1 id="folderTitle">Inbox</h1><p id="countLabel">Je berichten</p></div><div class="top-actions"><input id="search" placeholder="⌕  Zoek in e-mails…"><button id="refresh">↻</button><button id="themeBtn" title="Thema wisselen">◐</button></div></header>
<div class="workspace">
<section class="message-list"><div class="list-toolbar"><span>BERICHTEN</span><div class="selection-actions"><button id="selectAll">Selecteer</button><button id="deleteSelected" class="danger-action" disabled>Verwijderen</button></div></div><div id="messages" class="messages"><div class="empty">E-mails worden geladen…</div></div></section>
<section class="reader" id="reader"><div class="welcome"><div class="welcome-icon"><img src="assets/logo.svg" alt=""></div><h2>Welkom bij tfwbmail</h2><p>Selecteer een bericht om het te lezen.</p></div></section>
</div></main>
<div class="modal hidden" id="composeModal"><form id="composeForm" class="compose-window"><input type="hidden" name="draft_id" value=""><div class="modal-head"><b>Nieuw bericht</b><button type="button" class="close" id="closeCompose">×</button></div>
<label>Aan<input name="to" type="email" required placeholder="naam@voorbeeld.nl"></label><label>Onderwerp<input name="subject" placeholder="Onderwerp"></label><label>Bericht<textarea name="body" rows="12" required></textarea></label><label class="attach">Bijlage <input name="attachment" type="file"></label><div class="modal-foot"><span id="sendStatus"></span><button class="primary" type="submit">➤ Verzenden</button></div></form></div>
<div id="toast" class="toast"></div>
<div class="modal hidden" id="tutorialModal" role="dialog" aria-modal="true" aria-label="tfwbmail rondleiding">
  <div class="tutorial-card">
    <div class="tutorial-progress"><span class="active"></span><span></span><span></span></div>
    <div class="tutorial-logo"><img src="assets/logo.svg" alt="tfwbmail"></div>
    <h2 id="tutorialTitle">Welkom bij tfwbmail</h2>
    <p id="tutorialText">Je lokale postomgeving is klaar. Alle accounts en instellingen blijven op deze computer.</p>
    <div class="tutorial-actions"><button type="button" id="tutorialSkip">Overslaan</button><button type="button" class="primary" id="tutorialNext">Volgende</button></div>
  </div>
</div>
<script src="assets/app.js"></script></body></html>