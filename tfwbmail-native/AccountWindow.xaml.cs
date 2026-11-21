using System.Data;
using System.IO;
using System.Security.Cryptography;
using System.Text;
using System.Windows;
using MailKit.Net.Imap;
using MailKit.Security;
using Microsoft.Data.Sqlite;

namespace TfwbMail;

public partial class AccountWindow : Window
{
    private readonly string dataDirectory;

    public AccountWindow(string dataDirectory)
    {
        InitializeComponent();
        this.dataDirectory = dataDirectory;
        EmailBox.LostFocus += (_, _) => ApplyProviderPreset();
    }

    private void ApplyProviderPreset()
    {
        var domain = EmailBox.Text.Split('@').LastOrDefault()?.Trim().ToLowerInvariant();
        if (domain is "gmail.com")
        {
            ImapHostBox.Text = "imap.gmail.com"; ImapPortBox.Text = "993";
            SmtpHostBox.Text = "smtp.gmail.com"; SmtpPortBox.Text = "465";
        }
        else if (domain is "outlook.com" or "hotmail.com" or "live.com")
        {
            ImapHostBox.Text = "outlook.office365.com"; ImapPortBox.Text = "993";
            SmtpHostBox.Text = "smtp.office365.com"; SmtpPortBox.Text = "587";
        }
    }

    private async void SaveClick(object sender, RoutedEventArgs e)
    {
        SaveButton.IsEnabled = false;
        StatusText.Text = "IMAP-verbinding controleren...";
        try
        {
            using var client = new ImapClient();
            await client.ConnectAsync(ImapHostBox.Text.Trim(), int.Parse(ImapPortBox.Text),
                SecureSocketOptions.SslOnConnect);
            await client.AuthenticateAsync(EmailBox.Text.Trim(), PasswordBox.Password);
            client.Disconnect(true);

            var protectedPassword = ProtectedData.Protect(
                Encoding.UTF8.GetBytes(PasswordBox.Password),
                null,
                DataProtectionScope.CurrentUser);

            Directory.CreateDirectory(dataDirectory);
            var database = Path.Combine(dataDirectory, "tfwbmail-native.sqlite");
            using var connection = new SqliteConnection($"Data Source={database}");
            await connection.OpenAsync();
            using var command = connection.CreateCommand();
            command.CommandText = """
                CREATE TABLE IF NOT EXISTS account (
                    id INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    password BLOB NOT NULL,
                    imap_host TEXT NOT NULL,
                    imap_port INTEGER NOT NULL,
                    smtp_host TEXT NOT NULL,
                    smtp_port INTEGER NOT NULL
                );
                DELETE FROM account;
                INSERT INTO account(name,email,password,imap_host,imap_port,smtp_host,smtp_port)
                VALUES($name,$email,$password,$imap,$imapPort,$smtp,$smtpPort);
                """;
            command.Parameters.AddWithValue("$name", NameBox.Text.Trim());
            command.Parameters.AddWithValue("$email", EmailBox.Text.Trim());
            command.Parameters.AddWithValue("$password", protectedPassword);
            command.Parameters.AddWithValue("$imap", ImapHostBox.Text.Trim());
            command.Parameters.AddWithValue("$imapPort", int.Parse(ImapPortBox.Text));
            command.Parameters.AddWithValue("$smtp", SmtpHostBox.Text.Trim());
            command.Parameters.AddWithValue("$smtpPort", int.Parse(SmtpPortBox.Text));
            await command.ExecuteNonQueryAsync();

            DialogResult = true;
        }
        catch (Exception error)
        {
            StatusText.Text = $"Verbinding mislukt: {error.Message}";
            SaveButton.IsEnabled = true;
        }
    }
}
