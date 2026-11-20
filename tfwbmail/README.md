# tfwbmail

Een lokale, cloud-vrije mailomgeving met IMAP/SMTP, agenda, contacten,
conceptauto-save, offline cache en een Windows-startprogramma.

Een zelf-gehoste, open-source PHP-webmailclient met een inboxervaring die
bewust dicht aanleunt bij Outlook en Thunderbird. Dit project is een
zelfstandige basis; het is geen volledige feature-pariteit met die clients.

## Installeren met Docker (aanbevolen)

Vanaf de repositoryroot:

```sh
docker compose up -d --build
```

Open daarna <http://127.0.0.1:8090>. Voeg het Stalwart-account toe met:

- IMAP-server: `stalwart`
- IMAP-poort: `993`
- Beveiliging: SSL/TLS
- SMTP-server: `stalwart`
- SMTP-poort: `465`
- Beveiliging: SSL/TLS

Stalwart en tfwbmail kunnen via hun onderlinge Docker-netwerk communiceren.

## Installeren op Windows met XAMPP

1. Pak deze ZIP uit in `C:\xampp\htdocs\tb-mail`.
2. Open `C:\xampp\php\php.ini` in Kladblok.
3. Zoek `;extension=imap` en haal de puntkomma weg: `extension=imap`.
   - Als jouw PHP-versie geen IMAP-extensie bevat, installeer dan de passende IMAP-extensie voor exact jouw PHP-versie/architectuur of gebruik een PHP/XAMPP-build die IMAP ondersteunt.
4. Zorg dat deze extensies aan staan: `openssl`, `mbstring`, `pdo_sqlite`, `sqlite3`, `imap`.
5. Herstart Apache in het XAMPP Control Panel.
6. Open `http://localhost/tb-mail/`.
7. Voeg je e-mailaccount toe.

## XS4ALL / KPN mail

De gebruikelijke instellingen zijn:
- IMAP: `imap.xs4all.nl`, poort `993`, SSL/TLS
- SMTP: `smtp.xs4all.nl`, poort `465`, SSL/TLS

Gebruik je volledige e-mailadres als gebruikersnaam. Controleer de actuele serverinstellingen bij je provider; providers kunnen instellingen wijzigen. Sommige accounts vereisen een apart app-wachtwoord.

## GitHub

Upload de inhoud van deze map naar de root van een nieuwe GitHub-repository. Laat `data/` leeg in Git; de database en geheime sleutel worden lokaal aangemaakt.

## Beveiliging en beperkingen

- Wachtwoorden worden met AES-256-GCM versleuteld in een lokale SQLite-database. De sleutel staat in `data/.key`; maak hiervan een veilige back-up en deel deze nooit.
- Zet deze webapp niet onbeveiligd op het openbare internet. Gebruik HTTPS, sterke serverbeveiliging, updates en toegangsbeperkingen.
- Verwijder `data/` niet terwijl je de app gebruikt: daar staan database en sleutel.
- IMAP-mappen kunnen per provider een andere naam hebben. Deze versie gebruikt de gebruikelijke namen `INBOX`, `Sent`, `Drafts`, `Junk` en `Trash`.
- Deze eerste release ondersteunt lezen, zoeken binnen de geladen map, beantwoorden/doorsturen via een nieuw bericht, verzenden en één bijlage. Het is geen volledige Thunderbird-vervanger: geavanceerde MIME-bijlagen, meerdere accounts tegelijk, filters, labels, agenda, contactsynchronisatie, offline cache, OAuth2 en volledige mapdetectie zijn nog niet geïmplementeerd.
- HTML-mail wordt in een iframe-loze weergave getoond en beperkt opgeschoond. Open geen verdachte berichten of links.

## Vereisten

- Apache + PHP 8.1+
- PHP-extensies: `imap`, `openssl`, `mbstring`, `pdo_sqlite`, `sqlite3`
- Uitgaande verbinding naar de IMAP- en SMTP-server van je provider.

## Licentie

MIT — zie `LICENSE`.
