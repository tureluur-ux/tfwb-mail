using System.IO;
using System.Windows;
using Microsoft.Data.Sqlite;

namespace TfwbMail;

public partial class ContactsWindow : Window
{
    private readonly string database;

    public ContactsWindow(string dataDirectory)
    {
        InitializeComponent();
        Directory.CreateDirectory(dataDirectory);
        database = Path.Combine(dataDirectory, "tfwbmail-native.sqlite");
        Loaded += async (_, _) => await LoadAsync();
    }

    private async void AddClick(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrWhiteSpace(NameBox.Text) || string.IsNullOrWhiteSpace(EmailBox.Text)) return;
        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = """
            CREATE TABLE IF NOT EXISTS contacts(id INTEGER PRIMARY KEY,name TEXT,email TEXT);
            INSERT INTO contacts(name,email) VALUES($name,$email);
            """;
        command.Parameters.AddWithValue("$name", NameBox.Text.Trim());
        command.Parameters.AddWithValue("$email", EmailBox.Text.Trim());
        await command.ExecuteNonQueryAsync();
        NameBox.Clear(); EmailBox.Clear();
        await LoadAsync();
    }

    private async Task LoadAsync()
    {
        ContactList.Items.Clear();
        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = "CREATE TABLE IF NOT EXISTS contacts(id INTEGER PRIMARY KEY,name TEXT,email TEXT); SELECT id,name,email FROM contacts ORDER BY name";
        using var reader = await command.ExecuteReaderAsync();
        while (await reader.ReadAsync())
            ContactList.Items.Add($"{reader.GetString(1)}  -  {reader.GetString(2)}  (dubbelklik om te verwijderen)");
    }

    private async void DeleteClick(object sender, System.Windows.Input.MouseButtonEventArgs e)
    {
        var line = ContactList.SelectedItem?.ToString();
        if (line is null) return;
        var name = line.Split("  -  ").FirstOrDefault();
        var email = line.Split("  -  ").Skip(1).FirstOrDefault()?.Split("  (dubbelklik").FirstOrDefault();
        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = "DELETE FROM contacts WHERE name=$name AND email=$email";
        command.Parameters.AddWithValue("$name", name);
        command.Parameters.AddWithValue("$email", email);
        await command.ExecuteNonQueryAsync();
        await LoadAsync();
    }
}
