# Stalwart IMAP mailserver starter

Een eenvoudige, directe Docker Compose-starter voor
[Stalwart Mail Server](https://stalw.art/) met IMAP en SMTP.
Stalwart bewaart configuratie, accounts, e-mail en certificaten in het
persistente Docker-volume `stalwart-data`.

## Eigen domein

Je eigen domein is mogelijk. tfwbmail gebruikt een eigen hostname voor de
webinterface en Stalwart gebruikt hetzelfde domain for MX/IMAP/SMTP. Zet
`WEBMAIL_DOMAIN` in `.env` en maak een DNS `A`-/`AAAA`-record naar je server.

Start voor een domein de optionele Caddy-reverse-proxy:

```sh
docker compose --profile domain up -d --build
```

Caddy krijgt via Let's Encrypt automatisch een HTTPS-certificaat. Gebruik
bijvoorbeeld `mail.example.com` voor IMAP/SMTP en `webmail.example.com` voor
tfwbmail. Laat poorten `80` en `443` publiek bereikbaar. Stel een echte
mailserver nooit alleen op HTTP/domeinen uit deze examples.

## Vereisten

- Linux-host met Docker Engine en Docker Compose
- Een volledig gekwalificeerde mailhostnaam, bijvoorbeeld `mail.example.com`
- DNS-beheer voor die host en het e-maildomein
- Openstaande poorten `25`, `465`, `587`, `143`, `993` en `4190`
- Een publiek routbaar IP-adres voor productie

## Starten

```sh
cp .env.example .env
# Bewerk .env en zet MAIL_HOSTNAME op je echte hostnaam.
docker compose up -d
docker compose ps
```

Na het starten is de lokale tfwbmail-webmail beschikbaar op:

<http://127.0.0.1:8090>

De webmail-service wordt samen met Stalwart gestart en gebruikt het
`webmail-data`-volume voor zijn lokale database en versleutelingssleutel.
Voor het toevoegen van het account staan IMAP/SMTP standaard al op de Docker-
hostnaam `stalwart`. Gebruik een account en wachtwoord dat je in de
Stalwart-beheerinterface hebt aangemaakt.

Open de beheerinterface via SSH-tunnel; dit voorkomt blootstelling van de
administratorinterface aan het internet:

```sh
ssh -L 8080:127.0.0.1:8080 gebruiker@jouw-server
```

Open daarna <http://127.0.0.1:8080>. Gebruik voor het eerste account en
eerste configureren de wizard in Stalwarts beheerinterface.

Bij een eerste installatie geeft Stalwart een eenmalig beheerdersaccount of
installatietoken terug in de containerlogs:

```sh
docker compose logs --tail=100 stalwart
```

Stoppen en verwijderen van containers:

```sh
docker compose down
```

De mailboxgegevens blijven behouden bij `docker compose down`. Voor een
volledige verwijdering, inclusief e-mail en configuratie:

```sh
docker compose down -v
```

## Accountbeheer

Maak accounts en domeinen aan in de Stalwart-beheerinterface. Voeg ten minste
een account met een sterk, uniek wachtwoord toe voordat SMTP-authenticatie wordt
gebruikt. Het project bevat geen echte accounts of wachtwoorden.

Configureer in de beheerinterface:

1. Je maildomein, bijvoorbeeld `example.com`.
2. Een postvak/account, bijvoorbeeld `info@example.com`.
3. Het inkomende protocol als IMAP via poort `993`.
4. Uitgaande SMTP via poort `465` (implicit TLS) of `587` (STARTTLS).
5. Optioneel ManageSieve via poort `4190`.

Voor productie is SMTP-authenticatie en TLS voor alle gebruikersverkeer
verplicht. Laat geen onbekende netwerkbronnen ongecontroleerd relayen.

## DNS

Vervang `example.com` door je domein en `mail.example.com` door je echte
mailhost. Richt voor een eenvoudige setup beide op hetzelfde publieke IP.

| Doel | Type | Naam | Waarde |
|---|---|---|---|
| IMAP/SMTP-host | A | `mail` | publiek IPv4-adres |
| IPv6, indien beschikbaar | AAAA | `mail` | publiek IPv6-adres |
| Mailrouting | MX | `@` | `10 mail.example.com.` |
| SPF | TXT | `@` | `v=spf1 mx -all` |

Stalwart kan DKIM-sleutels genereren. Maak na het genereren exact het door
Stalwart getoonde `TXT`-record aan bij de aangegeven DKIM-selector. Een
verplichte DMARC-policy kan bestaan uit:

```text
v=DMARC1; p=reject; rua=mailto:dmarc@example.com
```

Voor betrouwbare levering moeten ook reverse DNS (PTR) voor het server-IP en
een goede SMTP-reputatie zijn geconfigureerd. DNS-validatie kan 24 uur
of langer duren.

## TLS

Stalwart ondersteunt certificaatbeheer via de beheerinterface en ACME,
bijvoorbeeld met Let's Encrypt. Gebruik een echte publieke hostname en
zorg dat poorten `80` en `443` bereikbaar zijn als ACME HTTP-validatie wordt
gebruikt. Raadpleeg de Stalwart-documentatie voor de ondersteunde ACME- en
certificaatinstellingen van de geïnstalleerde versie.

De Compose-opzet publiceert geen poort `443`; gebruik poort `443` pas nadat
Stalwart zelf of een reverse proxy die poort verzorgt en de beheerinterface
alleen via een tunnel of firewall bereikbaar blijft.

Stuur in mailclients de hostnaam `mail.example.com` en gebruik:

- IMAP: poort `993`, TLS
- SMTP-submission: poort `465`, implicit TLS, of poort `587`, STARTTLS
- Gebruiker: volledig e-mailadres

## Back-up en onderhoud

Maak regelmatig een back-up van het named volume:

```sh
docker run --rm \
  -v tfwb-mail_stalwart-data:/data:ro \
  -v "$PWD:/backup" \
  alpine tar czf /backup/stalwart-data.tar.gz -C /data .
```

Vervang bij een andere Compose-projectnaam eventueel `tfwb-mail_stalwart-data`
door het volume dat `docker volume ls` toont. Update gecontroleerd met:

```sh
docker compose pull
docker compose up -d
docker image prune
```

## Productiewaarschuwingen

- Dit is een generiek starterproject, geen beheerder- of security-hardeningsservice.
- Test back-up en herstel voordat echte mail wordt geaccepteerd.
- Configureer firewallregels; beperk poort `8080` aan lokaal netwerk/SSH-tunnel.
- Gebruik een unieke, sterke administrator-authenticatie en laat MFA/SSO toe
  waar Stalwart dit ondersteunt.
- Schakel ongebruikte endpoints uit, monitor logs, diskruimte en certificaatverloop.
- Pin voor productie idealiter een specifieke Stalwart-versie, en test upgrades
  in een stagingomgeving.
- Hostingproviders kunnen poort `25` blokkeren; test die poort bij de provider.
- Een mailserver heeft constante monitoring nodig voor misbruik, spam en
  resource-uitputting.
