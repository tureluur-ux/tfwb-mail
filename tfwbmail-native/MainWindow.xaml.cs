using System.Collections.ObjectModel;
using System.IO;
using System.Security.Cryptography;
using System.Text;
using System.Windows;
using System.Windows.Controls;
using MailKit;
using MailKit.Net.Imap;
using MailKit.Net.Smtp;
using MailKit.Search;
using MailKit.Security;
using Microsoft.Data.Sqlite;
using MimeKit;

namespace TfwbMail;

public partial class MainWindow : Window
{
    private readonly string dataDirectory = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "tfwbmail");
    private readonly ObservableCollection<MessageItem> messages = new();
    private Account? account;
    private string currentFolder = "INBOX";
    private MimeMessage? selectedMessage;

    public MainWindow()
    {
        InitializeComponent();
        MessageList.ItemsSource = messages;
        Loaded += async (_, _) => await InitializeAsync();
        Closed += (_, _) => Application.Current.Shutdown();
    }

    private async Task InitializeAsync()
    {
        account = await LoadAccountAsync();
        if (account is null)
        {
            ShowSettings();
            return;
        }
        await LoadFolderAsync(currentFolder);
    }

    private void SettingsClick(object sender, RoutedEventArgs e) => ShowSettings();

    private void ShowSettings()
    {
        var window = new AccountWindow(dataDirectory) { Owner = this };
        if (window.ShowDialog() == true)
        {
            _ = InitializeAsync();
        }
    }

    private async void FolderClick(object sender, RoutedEventArgs e)
    {
        if (sender is Button button)
        {
            currentFolder = button.Tag?.ToString() ?? "INBOX";
            await LoadFolderAsync(currentFolder);
        }
    }

    private async Task LoadFolderAsync(string folderName)
    {
        if (account is null) return;
        LoadingBar.Visibility = Visibility.Visible;
        messages.Clear();
        selectedMessage = null;
        ReadSubject.Text = "";
        ReadSender.Text = "";
        ReadBody.Text = "";
        try
        {
            using var client = new ImapClient();
            await client.ConnectAsync(account.ImapHost, account.ImapPort,
                SecureSocketOptions.SslOnConnect);
            await client.AuthenticateAsync(account.Email, account.Password);

            IMailFolder folder;
            try
            {
                folder = await client.GetFolderAsync(folderName);
                await folder.OpenAsync(FolderAccess.ReadWrite);
            }
            catch
            {
                folder = client.Inbox;
                await folder.OpenAsync(FolderAccess.ReadWrite);
            }

            var count = folder.Count;
            var from = Math.Max(0, count - 40);
            var summaries = await folder.FetchAsync(from, count - 1,
                MessageSummaryItems.Envelope | MessageSummaryItems.Flags | MessageSummaryItems.UniqueId);

            foreach (var summary in summaries.Reverse())
            {
                var preview = "Bericht laden...";
                messages.Add(new MessageItem
                {
                    UniqueId = summary.UniqueId,
                    Sender = summary.Envelope?.From?.Mailboxes.FirstOrDefault()?.Name
                             ?? summary.Envelope?.From?.Mailboxes.FirstOrDefault()?.Address
                             ?? "(onbekend)",
                    Subject = summary.Envelope?.Subject ?? "(zonder onderwerp)",
                    Preview = preview,
                    Folder = folder
                });
            }
            client.Disconnect(true);
        }
        catch (Exception error)
        {
            MessageBox.Show(this, $"IMAP-fout:\n{error.Message}", "tfwbmail",
                MessageBoxButton.OK, MessageBoxImage.Error);
        }
        finally
        {
            LoadingBar.Visibility = Visibility.Collapsed;
        }
    }

    private async void MessageSelected(object sender, SelectionChangedEventArgs e)
    {
        if (MessageList.SelectedItem is not MessageItem item) return;
        ComposePanel.Visibility = Visibility.Collapsed;
        ReaderActions.Visibility = Visibility.Visible;
        ReadSubject.Text = item.Subject;
        ReadSender.Text = item.Sender;
        ReadBody.Text = "Bericht laden...";
        try
        {
            using var client = new ImapClient();
            await client.ConnectAsync(account!.ImapHost, account.ImapPort,
                SecureSocketOptions.SslOnConnect);
            await client.AuthenticateAsync(account.Email, account.Password);
            await item.Folder.OpenAsync(FolderAccess.ReadWrite);
            selectedMessage = await item.Folder.GetMessageAsync(item.UniqueId);
            await item.Folder.AddFlagsAsync(item.UniqueId, MessageFlags.Seen, true);
            ReadBody.Text = selectedMessage.TextBody
                ?? StripHtml(selectedMessage.HtmlBody);
            ReadSender.Text = $"{item.Sender} — {selectedMessage.Date:dd MMM yyyy HH:mm}";
            client.Disconnect(true);
        }
        catch (Exception error)
        {
            ReadBody.Text = $"Bericht kon niet worden geladen:\n{error.Message}";
        }
    }

    private async void DeleteClick(object sender, RoutedEventArgs e)
    {
        if (MessageList.SelectedItem is not MessageItem item) return;
        try
        {
            using var client = new ImapClient();
            await client.ConnectAsync(account!.ImapHost, account.ImapPort,
                SecureSocketOptions.SslOnConnect);
            await client.AuthenticateAsync(account.Email, account.Password);
            await item.Folder.OpenAsync(FolderAccess.ReadWrite);
            await item.Folder.AddFlagsAsync(item.UniqueId, MessageFlags.Deleted, true);
            await item.Folder.ExpungeAsync(new[] { item.UniqueId });
            client.Disconnect(true);
            await LoadFolderAsync(currentFolder);
        }
        catch (Exception error)
        {
            MessageBox.Show(this, $"Verwijderen mislukt:\n{error.Message}", "tfwbmail",
                MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void ComposeClick(object sender, RoutedEventArgs e)
    {
        selectedMessage = null;
        ReaderActions.Visibility = Visibility.Collapsed;
        ReadSubject.Text = "";
        ReadSender.Text = "";
        ReadBody.Text = "";
        ComposePanel.Visibility = Visibility.Visible;
        ToBox.Text = "";
        SubjectBox.Text = "";
        BodyBox.Text = "";
        ToBox.Focus();
    }

    private void ReplyClick(object sender, RoutedEventArgs e)
    {
        if (selectedMessage is null) return;
        ReaderActions.Visibility = Visibility.Collapsed;
        ComposePanel.Visibility = Visibility.Visible;
        ToBox.Text = selectedMessage.From.Mailboxes.FirstOrDefault()?.Address ?? "";
        SubjectBox.Text = selectedMessage.Subject?.StartsWith("Re:", StringComparison.OrdinalIgnoreCase) == true
            ? selectedMessage.Subject
            : $"Re: {selectedMessage.Subject}";
        BodyBox.Text = "\n\n" + selectedMessage.TextBody;
        ToBox.Focus();
    }

    private async void SendClick(object sender, RoutedEventArgs e)
    {
        if (account is null) return;
        try
        {
            var message = new MimeMessage();
            message.From.Add(new MailboxAddress(account.Name, account.Email));
            message.To.Add(MailboxAddress.Parse(ToBox.Text.Trim()));
            message.Subject = SubjectBox.Text;
            message.Body = new TextPart("plain") { Text = BodyBox.Text };

            using var client = new SmtpClient();
            var smtpOptions = account.SmtpPort == 465
                ? SecureSocketOptions.SslOnConnect
                : SecureSocketOptions.StartTls;
            await client.ConnectAsync(account.SmtpHost, account.SmtpPort, smtpOptions);
            await client.AuthenticateAsync(account.Email, account.Password);
            await client.SendAsync(message);
            client.Disconnect(true);
            ComposePanel.Visibility = Visibility.Collapsed;
            ReaderActions.Visibility = Visibility.Visible;
            MessageBox.Show(this, "Bericht verzonden.", "tfwbmail",
                MessageBoxButton.OK, MessageBoxImage.Information);
        }
        catch (Exception error)
        {
            MessageBox.Show(this, $"Verzenden mislukt:\n{error.Message}", "tfwbmail",
                MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    private void SearchChanged(object sender, TextChangedEventArgs e)
    {
        var query = SearchBox.Text.Trim();
        foreach (var item in messages)
        {
            item.IsMatch = string.IsNullOrEmpty(query)
                || item.Subject.Contains(query, StringComparison.OrdinalIgnoreCase)
                || item.Sender.Contains(query, StringComparison.OrdinalIgnoreCase);
        }
        var matches = messages.Where(item => item.IsMatch).ToList();
        MessageList.ItemsSource = matches;
        if (string.IsNullOrEmpty(query)) MessageList.ItemsSource = messages;
    }

    private async Task<Account?> LoadAccountAsync()
    {
        Directory.CreateDirectory(dataDirectory);
        var database = Path.Combine(dataDirectory, "tfwbmail-native.sqlite");
        if (!File.Exists(database)) return null;

        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = "SELECT name,email,password,imap_host,imap_port,smtp_host,smtp_port FROM account LIMIT 1";
        using var reader = await command.ExecuteReaderAsync();
        if (!await reader.ReadAsync()) return null;

        var passwordBytes = ProtectedData.Unprotect(
            (byte[])reader["password"], null, DataProtectionScope.CurrentUser);
        return new Account
        {
            Name = reader.GetString(0),
            Email = reader.GetString(1),
            Password = Encoding.UTF8.GetString(passwordBytes),
            ImapHost = reader.GetString(3),
            ImapPort = reader.GetInt32(4),
            SmtpHost = reader.GetString(5),
            SmtpPort = reader.GetInt32(6)
        };
    }

    private static string StripHtml(string? html)
    {
        if (string.IsNullOrWhiteSpace(html)) return "";
        return System.Text.RegularExpressions.Regex.Replace(html, "<[^>]+>", " ");
    }

    private sealed class Account
    {
        public required string Name { get; init; }
        public required string Email { get; init; }
        public required string Password { get; init; }
        public required string ImapHost { get; init; }
        public required int ImapPort { get; init; }
        public required string SmtpHost { get; init; }
        public required int SmtpPort { get; init; }
    }

    public sealed class MessageItem
    {
        public UniqueId UniqueId { get; init; }
        public required string Sender { get; init; }
        public required string Subject { get; init; }
        public required string Preview { get; init; }
        public required IMailFolder Folder { get; init; }
        public bool IsMatch { get; set; } = true;
    }
}
