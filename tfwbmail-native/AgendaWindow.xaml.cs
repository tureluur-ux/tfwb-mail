using System.IO;
using System.Windows;
using Microsoft.Data.Sqlite;

namespace TfwbMail;

public partial class AgendaWindow : Window
{
    private readonly string database;

    public AgendaWindow(string dataDirectory)
    {
        InitializeComponent();
        Directory.CreateDirectory(dataDirectory);
        database = Path.Combine(dataDirectory, "tfwbmail-native.sqlite");
        Loaded += async (_, _) => await LoadAsync();
    }

    private async void AddClick(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrWhiteSpace(TitleBox.Text) || DateBox.SelectedDate is null) return;
        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = """
            CREATE TABLE IF NOT EXISTS agenda(id INTEGER PRIMARY KEY,title TEXT,event_date TEXT);
            INSERT INTO agenda(title,event_date) VALUES($title,$date);
            """;
        command.Parameters.AddWithValue("$title", TitleBox.Text.Trim());
        command.Parameters.AddWithValue("$date", DateBox.SelectedDate.Value.ToString("yyyy-MM-dd"));
        await command.ExecuteNonQueryAsync();
        TitleBox.Clear();
        await LoadAsync();
    }

    private async Task LoadAsync()
    {
        EventList.Items.Clear();
        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = "CREATE TABLE IF NOT EXISTS agenda(id INTEGER PRIMARY KEY,title TEXT,event_date TEXT); SELECT id,title,event_date FROM agenda ORDER BY event_date";
        using var reader = await command.ExecuteReaderAsync();
        while (await reader.ReadAsync())
            EventList.Items.Add($"{reader.GetString(2)}  -  {reader.GetString(1)}  (dubbelklik om te verwijderen)");
    }

    private async void DeleteClick(object sender, System.Windows.Input.MouseButtonEventArgs e)
    {
        var line = EventList.SelectedItem?.ToString();
        if (line is null) return;
        var date = line[..10];
        var title = line.Split("  -  ").Skip(1).FirstOrDefault()?.Split("  (dubbelklik").FirstOrDefault();
        using var connection = new SqliteConnection($"Data Source={database}");
        await connection.OpenAsync();
        using var command = connection.CreateCommand();
        command.CommandText = "DELETE FROM agenda WHERE event_date=$date AND title=$title";
        command.Parameters.AddWithValue("$date", date);
        command.Parameters.AddWithValue("$title", title);
        await command.ExecuteNonQueryAsync();
        await LoadAsync();
    }
}
