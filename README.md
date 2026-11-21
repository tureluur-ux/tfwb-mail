# tfwbmail

**A local-first email desktop for Windows.**

tfwbmail is a native .NET 8 / WPF mail application with direct IMAP and SMTP
support. It is designed to feel like a complete desktop mail client, while
keeping account data, settings, agenda and contacts on the user's own device.

No tfwbmail account. No tfwbmail cloud. No XAMPP. No Apache. No PHP runtime.
No browser window and no localhost mail server are required for the native app.

**Latest release:** [tfwbmail v1.100.2](https://github.com/tureluur-ux/tfwb-mail/releases/tag/v1.100.2)

## Download

Download the native Windows executable:

**[Download tfwbmail.exe](https://github.com/tureluur-ux/tfwb-mail/releases/download/v1.100.2/tfwbmail.exe)**

The executable is self-contained for `win-x64` and starts directly. It uses
MailKit for IMAP/SMTP and Microsoft.Data.Sqlite for local data. No installer,
XAMPP installation or internet account is required for tfwbmail itself.

The EXE is approximately 166 MB because it includes the .NET runtime and native
dependencies.

### Windows startup

```text
1. Download tfwbmail.exe.
2. Verify the SHA-256 checksum shown below.
3. Double-click the executable.
4. Enter the account and server details.
5. tfwbmail validates the IMAP login before saving the account.
```

You can also start it from the repository:

```bat
start-tfwbmail.bat
```

## Local-first privacy

The native application does not send account details or mail credentials to
tfwbmail, GitHub or a tfwbmail backend.

| Local item | Location |
|---|---|
| Account database | `%LOCALAPPDATA%\tfwbmail\tfwbmail-native.sqlite` |
| Protected password | Stored inside the SQLite database with Windows DPAPI |
| Agenda | Same local SQLite database |
| Contacts | Same local SQLite database |
| Sidebar preference | `%LOCALAPPDATA%\tfwbmail\sidebar-collapsed` |

The Windows DPAPI password protection is tied to the current Windows user.
Protecting the account database should include securing the Windows profile
and maintaining a secure backup.

Mail itself remains on the configured mail provider or on a server operated by
the user. tfwbmail only communicates directly with:

- the IMAP server for folders and messages;
- the SMTP server for outgoing mail;
- DNS and provider services used by the chosen mail provider.

There is no mandatory tfwbmail cloud synchronization.

## Native features

### Mail

- Inbox and folder navigation;
- Direct IMAP connection;
- Direct SMTP sending;
- Search by sender or subject;
- Read message view;
- Reply flow;
- Message deletion;
- Message selection;
- Compact message list;
- Incoming folder loading;
- Sent, drafts and trash folder names;
- TLS/SSL and STARTTLS support;
- Account validation before saving;
- Gmail and Microsoft-style provider defaults.

### Interface

- Native tfwbmail desktop window;
- Custom tfwbmail envelope logo;
- Dark, low-glare layout;
- Persistent collapsible sidebar;
- Compact icon-style navigation;
- Large desktop workspace;
- Responsive minimum window size;
- Account, inbox, sent, draft and trash navigation;
- Agenda and contacts panels;
- Local keyboard shortcut: `Ctrl+N` opens a new message.

### Local productivity

- Personal agenda stored in SQLite;
- Add and delete agenda entries;
- Personal contacts stored in SQLite;
- Add and delete contacts;
- Account settings without a cloud account;
- Local sidebar state.

### Security and account handling

- Direct IMAP authentication;
- Direct SMTP authentication;
- Password protection with Windows DPAPI;
- Account is not saved until IMAP login succeeds;
- SSL/TLS for IMAP port 993;
- SSL/TLS or STARTTLS for SMTP;
- App-password support for providers that require it;
- No secret values are committed to this repository.

## Account setup

On the first start, the account window asks for:

| Field | Example |
|---|---|
| Name | User name |
| Email address | user@example.com |
| Password | Mail-provider password or app password |
| IMAP host | imap.example.com |
| IMAP port | 993 |
| SMTP host | smtp.example.com |
| SMTP port | 465 or 587 |

The native app first opens an encrypted-TLS IMAP connection and authenticates
the account. Only if authentication succeeds does it save the account.

### Common provider settings

| Provider | IMAP | SMTP |
|---|---|---|
| Gmail | `imap.gmail.com:993` with SSL/TLS | `smtp.gmail.com:465` with SSL/TLS |
| Microsoft 365 / Outlook | `outlook.office365.com:993` with SSL/TLS | `smtp.office365.com:587` with STARTTLS |
| XS4ALL | `imap.xs4all.nl:993` with SSL/TLS | `smtp.xs4all.nl:465` with SSL/TLS |
| KPN | `imap.kpnmail.nl:993` with SSL/TLS | `smtp.kpnmail.nl:465` with SSL/TLS |
| Stalwart / self-hosted | `<domain>:993` with SSL/TLS | `<domain>:465` with SSL/TLS |

Provider rules can change. Gmail and Microsoft accounts may require an app
password or additional account authorization.

Use the full email address as the IMAP and SMTP username. Do not enter a
server administrator password as a normal mailbox password.

## Build from source

Requirements:

- Windows 10 or 11;
- .NET 8 SDK;
- Visual Studio or WPF-capable build tooling;
- an internet connection for the initial NuGet restore.

Build and publish the native executable:

```powershell
dotnet publish tfwbmail-native/tfwbmail-native.csproj `
  -c Release `
  -r win-x64 `
  --self-contained true `
  -p:PublishSingleFile=true `
  -p:IncludeNativeLibrariesForSelfExtract=true `
  -o tfwbmail-native/publish
```

The packaged executable is:

```text
tfwbmail-native/publish/tfwbmail.exe
```

## Optional Stalwart mail server

This repository also contains an optional Docker Compose setup for running
Stalwart Mail Server. The native app can connect to Stalwart, but Docker is not
needed for the desktop application itself.

Create local environment settings:

```sh
cp .env.example .env
```

Set at least:

```env
MAIL_HOSTNAME=mail.example.com
TZ=Europe/Amsterdam
```

Start Stalwart:

```sh
docker compose up -d --build
docker compose ps
```

Stalwart persists mail and configuration in Docker volume
`stalwart-data`.

## Optional Docker webmail

The repository keeps the original webmail and Docker files for optional
self-hosted deployment. It is separate from the native Windows application.

The webmail service listens on loopback:

```text
http://127.0.0.1:8090
```

Docker volumes:

| Volume | Purpose |
|---|---|
| `stalwart-data` | Stalwart configuration, mailboxes and certificates |
| `webmail-data` | Optional webmail SQLite data and key |
| `caddy-data` | Optional Caddy certificates |
| `caddy-config` | Optional Caddy configuration |

Stop containers:

```sh
docker compose down
```

Remove containers and all data:

```sh
docker compose down -v
```

This data-deletion command is destructive.

## Custom domain and DNS

For a public Stalwart mail server, the DNS owner must provide and control:

| Record | Purpose | Example |
|---|---|---|
| A | IPv4 mail host | `mail.example.com -> public IPv4` |
| AAAA | IPv6 mail host | `mail.example.com -> public IPv6` |
| MX | Mail routing | `10 mail.example.com.` |
| SPF TXT | Outbound sender authorization | `v=spf1 mx -all` |
| DKIM TXT | Signed outgoing mail | Value supplied by Stalwart |
| DMARC TXT | Policy and reports | `v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com` |

For normal production delivery:

- configure reverse DNS/PTR for the outbound public IP;
- open SMTP port `25`;
- open submission ports `587` and/or `465`;
- open IMAP port `993`;
- configure SPF, DKIM and DMARC;
- test TLS certificates;
- test sending and receiving before users depend on the server;
- monitor disk usage and create restore-tested backups.

Optional HTTPS for the webmail:

```env
MAIL_HOSTNAME=mail.example.com
WEBMAIL_DOMAIN=webmail.example.com
TZ=Europe/Amsterdam
```

```sh
docker compose --profile domain up -d --build
```

Ports `80` and `443` must be reachable for Caddy ACME certificates.

## Backup

Native Windows backup:

```powershell
$backup = "$env:USERPROFILE\Backups\tfwbmail"
New-Item -ItemType Directory -Force -Path $backup | Out-Null
Copy-Item "$env:LOCALAPPDATA\tfwbmail\tfwbmail-native.sqlite" `
  "$backup\tfwbmail-native.sqlite"
```

Store the backup on an encrypted, trusted and restorable location. Test the
backup on another Windows profile or system before relying on it.

Do not copy the database while tfwbmail is actively writing to it.

Docker backup:

```sh
docker run --rm \
  -v tfwb-mail_stalwart-data:/data:ro \
  -v "$PWD:/backup" \
  alpine tar czf /backup/stalwart-data.tar.gz -C /data .
```

## Troubleshooting

### IMAP connection fails

- verify the hostname and port;
- use `993` with SSL/TLS for normal IMAP;
- use the full email address as username;
- try an app password;
- verify that MFA/provider security settings allow the login;
- check firewall and internet filtering.

### SMTP sending fails

- verify the SMTP hostname;
- try port `587` with STARTTLS;
- try port `465` with implicit TLS;
- verify account and app-password policy;
- verify provider or server rate limits.

### Messages do not appear

- verify that the account is active;
- refresh the selected folder;
- check provider folder names;
- providers may name sent, junk and trash folders differently;
- verify that the account has the required IMAP permissions.

### Windows reports a Smart App Control warning

Windows may block a locally built executable. Verify the downloaded SHA-256
hash before running it. Distribution to organizations should use signing and
a trusted software-release process.

## Release integrity

Release:

<https://github.com/tureluur-ux/tfwb-mail/releases/tag/v1.100.2>

Executable:

<https://github.com/tureluur-ux/tfwb-mail/releases/download/v1.100.2/tfwbmail.exe>

Verify the downloaded file:

```powershell
Get-FileHash .\tfwbmail.exe -Algorithm SHA256
```

Official v1.100.2 SHA-256:

```text
FAA69B35C032F7E3BACFBEE319C644B3CEFB1D58ED13E455B8521575AFECB597
```

## Security boundaries

tfwbmail is not an email-server appliance and does not replace DNS, provider,
TLS or SMTP reputation configuration.

Important operator responsibilities:

- keep Windows, .NET and mail providers updated;
- use strong unique passwords;
- use app passwords when required;
- keep the local database and backups private;
- use only trusted mail servers and DNS providers;
- test spam, SPF, DKIM and DMARC behavior;
- monitor Stalwart and Docker logs;
- maintain an exit and restore plan.

## License

MIT. See [LICENSE](LICENSE).
