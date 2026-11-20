# tfwbmail

> Een snelle, lokale en cloud-vrije mailomgeving voor Windows en Docker.

tfwbmail is een complete webmailstarter rond **Stalwart Mail Server**.
Berichten worden via IMAP opgehaald en via SMTP verzonden. Accounts,
agenda, contacten, concepten en lokale cache blijven op jouw server of
computer; er is geen tfwbmail-cloudaccount en geen verplichte externe
autoconfiguratieservice.

**Release:** [tfwbmail v1.100.2](https://github.com/tureluur-ux/tfwb-mail/releases/tag/v1.100.2)

---

## Wat zit erin

- Moderne Outlook-/Thunderbird-achtige interface
- Volledig lokaal accountbeheer
- Stalwart-, XS4ALL-, KPN-, Microsoft 365-/Outlook- en Gmail-presets
- Handmatige IMAP/SMTP-instellingen voor andere providers
- IMAP-mappen: inbox, verzonden, concepten, spam en prullenbak
- Berichten lezen, zoeken, selecteren, beantwoorden, doorsturen en verzenden
- Conceptauto-save en lokale berichtencache
- Bijlagen versturen
- Lokale agenda met evenementen
- Lokale contactenlijst
- Compact inklapbare zijbalk en responsieve mobiele weergave
- Licht en donker thema
- Eerste-start tutorial
- Veilige opslag van mailwachtwoorden met AES-256-GCM
- Windows EXE en directe batchstarter
- Optionele Docker Compose-stack met eigen HTTPS-domein via Caddy

## Windows-snelstart

Download de EXE uit [release v1.100.2](https://github.com/tureluur-ux/tfwb-mail/releases/tag/v1.100.2)
en dubbelklik op `tfwbmail.exe`.

De launcher is een echte Windows-desktopapp. Het mailvenster opent niet als
los browservenster: de lokale webinterface wordt in een ingebouwd WebView2-
venster getoond.

1. controleert of PHP met IMAP/SQLite en WebView2 al geïnstalleerd zijn;
2. toont bij ontbrekende onderdelen een installatie-widget met **Installeren**
   of **Annuleren**;
3. voert WinGet alleen uit nadat je op **Installeren** hebt geklikt;
4. start de lokale mailservice op `127.0.0.1:8090`;
5. opent één eigen tfwbmail-appvenster.

De eerste start vereist Microsoft Edge WebView2 Runtime, die op recente
Windows 10/11-installaties meestal al aanwezig is. Als Windows die runtime
mist, installeer dan de officiële WebView2 Runtime en start de EXE opnieuw.

De batchstarter blijft beschikbaar voor diagnose en ontwikkelaars:

<http://127.0.0.1:8090>

Alternatief vanuit de repository:

```bat
start-tfwbmail.bat
```

## Eenmalige accountinstelling

Bij de eerste start:

1. kies **Automatisch op basis van e-mailadres** of een provider;
2. vul naam, e-mailadres en wachtwoord in;
3. controleer de automatisch ingevulde IMAP/SMTP-server, poort en TLS;
4. klik **Account opslaan en openen**.

De app probeert eerst een echte IMAP-verbinding. Een account wordt pas
opgeslagen als inloggen met het mailwachtwoord lukt.

| Provider | IMAP | SMTP |
|---|---|---|
| XS4ALL | imap.xs4all.nl:993 SSL/TLS | smtp.xs4all.nl:465 SSL/TLS |
| KPN | imap.kpnmail.nl:993 SSL/TLS | smtp.kpnmail.nl:465 SSL/TLS |
| Microsoft 365/Outlook | outlook.office365.com:993 SSL/TLS | smtp.office365.com:587 STARTTLS |
| Gmail | imap.gmail.com:993 SSL/TLS | smtp.gmail.com:465 SSL/TLS |
| Stalwart/zelf gehost | `stalwart`:993 SSL/TLS | `stalwart`:465 SSL/TLS |

Gebruik voor Gmail een app-wachtwoord als Google-accountcontrole dit vereist.

## Lokale gegevens en privacy

tfwbmail gebruikt geen externe tfwbmail-backend. Voor lokale executie gelden:

- accounts en wachtwoordcijfers: `tfwbmail/data/tbmail.sqlite`
- versleutelingssleutel: `tfwbmail/data/.key`
- agenda en contacten: dezelfde lokale SQLite-database
- concepten en berichtcache: dezelfde lokale SQLite-database
- browser-sessie: lokale PHP-sessioncookie

Deze bestanden blijven op de eigen computer. Maak regelmatig een beveiligde
back-up van **zowel** `tbmail.sqlite` als `.key`. Alleen een back-up van de
database is onvoldoende: zonder de sleutel kunnen bestaande wachtwoorden
niet worden ontsleuteld.

Volledig resetten kan via **Lokale gegevens resetten** op de setup-/loginpagina.
Hiermee worden alle lokale accounts, wachtwoorden, agenda, contacten en cache
verwijderd. Deze actie is onomkeerbaar.

## Docker / Stalwart-snelstart

Op een Linux- of Docker-host:

```sh
cp .env.example .env
# Vul MAIL_HOSTNAME en eventueel WEBMAIL_DOMAIN in.
docker compose up -d --build
docker compose ps
```

Open lokaal:

<http://127.0.0.1:8090>

Stalwart-beheer:

```sh
ssh -L 8080:127.0.0.1:8080 gebruiker@server
```

Open daarna via de SSH-tunnel:

<http://127.0.0.1:8080>

Stalwart en tfwbmail delen het Compose-netwerk. In tfwbmail worden hostnamen
`stalwart`, poort `993` en poort `465` automatisch gebruikt.

### Volumes

- `stalwart-data`: Stalwart-configuratie, e-mail, accounts en certificaten
- `webmail-data`: tfwbmail-database en versleutelingssleutel
- `caddy-data`/`caddy-config`: optionele HTTPS-certificaten en Caddy-configuratie

Containers stoppen:

```sh
docker compose down
```

Alle account- en mailboxdata verwijderen:

```sh
docker compose down -v
```

## Eigen domein en HTTPS

tfwbmail kan achter een eigen domein draaien via Caddy. Stel in `.env`:

```env
MAIL_HOSTNAME=mail.example.com
WEBMAIL_DOMAIN=mail.example.com
TZ=Europe/Amsterdam
```

Start met het optionele domeinprofiel:

```sh
docker compose --profile domain up -d --build
```

Caddy ontvangt poorten `80` en `443` en haalt automatisch een
Let's Encrypt-certificaat op. Voor gescheiden hostnamen kan bijvoorbeeld:

- `mail.example.com`: IMAP/SMTP en Stalwart
- `webmail.example.com`: tfwbmail-webinterface

De DNS voor beide hostnamen moet naar het publieke serveradres wijzen.
Poort `25`, `465`, `587`, `143`, `993` en `4190` moeten voor mailproductie
bereikbaar zijn; poorten `80` en `443` zijn nodig voor HTTPS/ACME.

## DNS voor een Stalwart-domein

Vervang de voorbeelden door je eigen domein en public IP.

| Doel | Record | Naam | Waarde |
|---|---|---|---|
| Mailserver | A | `mail` | publiek IPv4-adres |
| Mailserver IPv6 | AAAA | `mail` | publiek IPv6-adres |
| Mailrouting | MX | `@` | `10 mail.example.com.` |
| SPF | TXT | `@` | `v=spf1 mx -all` |
| Webmail | CNAME of A | `webmail` | serveradres |

Genereer DKIM via Stalwart en publiceer exact het door Stalwart getoonde
selector/waarde-record. Zet daarna ook een DMARC-record:

```text
v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com
```

Na een stabiele observatieperiode kan `p=reject` worden gebruikt.
Reverse DNS/PTR voor het uitgaande SMTP-IP is voor betrouwbare levering ook
noodzakelijk.

## IMAP/SMTP-configuratie

- IMAP: poort `993` met implicit TLS
- STARTTLS: poort `143` voor IMAP indien ondersteund
- SMTP submission: poort `587` met STARTTLS
- Implicit SMTP TLS: poort `465`
- SMTP relay: poort `25`

Gebruik voor IMAP/SMTP altijd het volledige e-mailadres als gebruikersnaam.
Gebruik geen beheerderswachtwoord voor een gewoon account. Sommige providers
vereisen app-wachtwoorden of MFA/OAuth; providerinstellingen kunnen wijzigen.

## Beveiligingsmodel

- IMAP- en SMTP-wachtwoorden worden alleen lokaal versleuteld opgeslagen
- Geen accountgegevens naar tfwbmail, GitHub of een externe cloudservice
- IMAP-verbindingen en SMTP-verkeer gebruiken TLS door configuratie
- De beheerinterface van Stalwart wordt niet publiek gepubliceerd
- De tfwbmail-SQLitegegevensmap wordt door `.htaccess`/Docker omheining afgeschermd
- HTML-mail wordt opgeschoond; scripts en actieve embed-content worden niet uitgevoerd
- Docker gebruikt `no-new-privileges`
- Gebruik HTTPS voor internettoegang
- Zorg voor firewall, updates, sterke wachtwoorden en back-uphersteltests

## Back-up

Windows back-up:

```powershell
Copy-Item tfwbmail\data\tbmail.sqlite "$env:USERPROFILE\Backups\tbmail.sqlite"
Copy-Item tfwbmail\data\.key "$env:USERPROFILE\Backups\tfwbmail.key"
```

Bewaar beide bestanden op een versleutelde, beveiligde back-uplocatie.

Docker-volume-back-up:

```sh
docker run --rm \
  -v tfwb-mail_stalwart-data:/data:ro \
  -v "$PWD:/backup" \
  alpine tar czf /backup/stalwart-data.tar.gz -C /data .
```

## Troubleshooting

### IMAP-verbindingsfout

- Controleer hostnaam, poort en SSL/STARTTLS;
- gebruik het volledige e-mailadres;
- probeer een app-wachtwoord;
- controleer firewall en provideraccountblokkade;
- activeer of installeer de PHP `imap`-extensie.

### SQLite `could not find driver`

Laad bij Windows-PHP samen:

```text
php_pdo_sqlite.dll
php_sqlite3.dll
php_imap.dll
php_openssl.dll
php_mbstring.dll
```

De meegeleverde `start-tfwbmail.bat` en EXE zoeken automatisch naar een
PHP-runtime met deze modules.

### Berichten verschijnen niet

- controleer provideraccount en mapnamen;
- log opnieuw in;
- controleer internetverbinding;
- laad inbox opnieuw;
- IMAP-mapping kan `Sent`, `Junk` of `Trash` anders noemen.

### De EXE wordt geblokkeerd

Windows Smart App Control of antivirusbeleid kan een lokaal gebouwd EXE
blokkeer. Controleer alleen de SHA-256 en signeer of packageer de EXE voor
organisatiebrede distributie in een vertrouwde uitgifteomgeving.

## Ontwikkelen

PHP-syntaxcontrole:

```sh
Get-ChildItem -Recurse -Filter *.php tfwbmail |
  ForEach-Object { php -l $_.FullName }
```

JavaScript-syntaxcontrole:

```sh
node --check tfwbmail/assets/app.js
node --check tfwbmail/assets/calendar.js
node --check tfwbmail/assets/contacts.js
```

Windows-EXE bouwen:

```sh
dotnet publish tfwbmail/launcher.csproj -c Release -r win-x64 `
  --self-contained true `
  -p:PublishSingleFile=true `
  -p:IncludeNativeLibrariesForSelfExtract=true `
  -o tfwbmail/downloads
```

## Release

Versie 1.100.2:

<https://github.com/tureluur-ux/tfwb-mail/releases/tag/v1.100.2>

Het Windows-EXE-asset is beschikbaar via de release:

<https://github.com/tureluur-ux/tfwb-mail/releases/download/v1.100.2/tfwbmail.exe>

### SHA-256-integriteit

Controleer het gedownloade EXE-bestand:

```powershell
Get-FileHash .\tfwbmail.exe -Algorithm SHA256
```

Officiële v1.100.2-hash:

```text
F72D71FF5573E24912D05E4509FF1CDED576BD31589BB08A589CACD69974AA5A
```

## Licentie

MIT — zie `LICENSE`.
