<?php
require __DIR__.'/../lib.php';
boot();
require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Ongeldige aanvraag.');
    }
    csrf_check();
    $a = account();
    $uids = array_values(array_filter(array_map('intval', $_POST['uids'] ?? [])));
    $folder = preg_replace('/[^A-Za-z0-9 _-]/', '', $_POST['folder'] ?? 'INBOX');
    if (!$uids) {
        throw new RuntimeException('Selecteer ten minste één bericht.');
    }

    $imap = imap_connection($a);
    $path = '{'.$a['imap_host'].':'.$a['imap_port'].'/imap'.($a['imap_security'] === 'ssl' ? '/ssl' : '/tls').'/novalidate-cert}'.$folder;
    if (!imap_reopen($imap, $path)) {
        throw new RuntimeException('Map niet gevonden.');
    }
    foreach ($uids as $uid) {
        if (strcasecmp($folder, 'Trash') === 0) {
            imap_delete($imap, (string) $uid, FT_UID);
        } else {
            $trash='Trash';
            $boxes=imap_getmailboxes($imap,'{'.$a['imap_host'].'}','*')?:[];
            foreach($boxes as $box){
                $name=substr($box->name,strrpos($box->name,'}')+1);
                if(stripos($name,'trash')!==false||stripos($name,'prullenbak')!==false){$trash=$name;break;}
            }
            imap_mail_move($imap,(string)$uid,$trash,CP_UID);
        }
    }
    imap_expunge($imap);
    imap_close($imap);
    echo json_encode(['ok' => true, 'message' => 'Berichten verwijderd.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
