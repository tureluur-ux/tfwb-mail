using System.Diagnostics;
using System.Drawing;
using System.Net;
using System.Windows.Forms;
using Microsoft.Web.WebView2.WinForms;

internal static class Program
{
    private const string Host = "127.0.0.1";
    private const int Port = 8090;
    private static string Url => $"http://{Host}:{Port}/";

    [STAThread]
    private static async Task Main()
    {
        ApplicationConfiguration.Initialize();

        if (await IsServerReady(Url))
        {
            Application.Run(new MailWindow(Url, null));
            return;
        }

        var php = FindPhp() ?? InstallPhp();
        var baseDirectory = AppContext.BaseDirectory.TrimEnd('\\', '/');
        var webRoot = Path.GetFileName(baseDirectory).Equals("downloads", StringComparison.OrdinalIgnoreCase)
            ? Directory.GetParent(baseDirectory)?.FullName ?? baseDirectory
            : baseDirectory;

        if (php is null)
        {
            ShowError("PHP met IMAP en SQLite kon niet automatisch worden geïnstalleerd. " +
                      "Installeer PHP 8.3 via WinGet en start tfwbmail opnieuw.");
            return;
        }

        var phpDirectory = Path.GetDirectoryName(php) ?? "";
        var extensionDirectory = Path.Combine(phpDirectory, "ext");
        var arguments = $"-d \"extension_dir={extensionDirectory}\" " +
                        "-d extension=php_openssl.dll " +
                        "-d extension=php_mbstring.dll " +
                        "-d extension=php_pdo_sqlite.dll " +
                        "-d extension=php_sqlite3.dll " +
                        "-d extension=php_imap.dll " +
                        $"-S {Host}:{Port}";
        var server = new Process
        {
            StartInfo = new ProcessStartInfo
            {
                FileName = php,
                Arguments = arguments,
                WorkingDirectory = webRoot,
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardError = true
            }
        };

        try
        {
            server.Start();
        }
        catch (Exception error)
        {
            ShowError($"De lokale server kon niet starten:\n{error.Message}");
            return;
        }

        if (!await WaitForServer(Url, server, TimeSpan.FromSeconds(20)))
        {
            var details = await server.StandardError.ReadToEndAsync();
            server.Kill(true);
            ShowError($"tfwbmail startte niet op poort 8090.\n{details}");
            return;
        }

        Application.Run(new MailWindow(Url, server));
    }

    private static string? FindPhp()
    {
        var packageRoot = Environment.ExpandEnvironmentVariables(
            @"%LOCALAPPDATA%\Microsoft\WinGet\Packages");
        if (Directory.Exists(packageRoot))
        {
            var phpWithImap = Directory.EnumerateFiles(packageRoot, "php.exe", SearchOption.AllDirectories)
                .FirstOrDefault(HasImap);
            if (phpWithImap is not null) return phpWithImap;
        }

        var links = Environment.ExpandEnvironmentVariables(
            @"%LOCALAPPDATA%\Microsoft\WinGet\Links\php.exe");
        if (HasImap(links)) return links;

        var candidates = new[]
        {
            @"C:\php\php.exe",
            @"C:\Program Files\PHP\php.exe",
            @"C:\xampp\php\php.exe"
        };
        return candidates.FirstOrDefault(HasImap);
    }

    private static bool HasImap(string file)
    {
        if (!File.Exists(file)) return false;
        var directory = Path.GetDirectoryName(file) ?? "";
        return File.Exists(Path.Combine(directory, "ext", "php_imap.dll"));
    }

    private static string? InstallPhp()
    {
        var winget = FindOnPath("winget.exe");
        if (winget is null) return null;

        try
        {
            using var install = Process.Start(new ProcessStartInfo
            {
                FileName = winget,
                Arguments = "install --id PHP.PHP.8.3 --exact --silent " +
                            "--accept-source-agreements --accept-package-agreements",
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            });
            install?.WaitForExit((int)TimeSpan.FromMinutes(5).TotalMilliseconds);
        }
        catch
        {
            return null;
        }

        return FindPhp();
    }

    private static string? FindOnPath(string fileName)
    {
        var path = Environment.GetEnvironmentVariable("PATH") ?? "";
        return path.Split(Path.PathSeparator)
            .Select(directory => Path.Combine(directory.Trim(), fileName))
            .FirstOrDefault(File.Exists);
    }

    private static async Task<bool> WaitForServer(string serverUrl, Process server, TimeSpan timeout)
    {
        var deadline = DateTime.UtcNow + timeout;
        while (DateTime.UtcNow < deadline && !server.HasExited)
        {
            if (await IsServerReady(serverUrl)) return true;
            await Task.Delay(250);
        }
        return false;
    }

    private static async Task<bool> IsServerReady(string serverUrl)
    {
        try
        {
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(1) };
            using var response = await client.GetAsync(serverUrl);
            return response.StatusCode is HttpStatusCode.OK or HttpStatusCode.Found;
        }
        catch
        {
            return false;
        }
    }

    private static void ShowError(string text)
    {
        MessageBox.Show(text, "tfwbmail", MessageBoxButtons.OK, MessageBoxIcon.Error);
    }
}

internal sealed class MailWindow : Form
{
    private readonly Process? server;
    private readonly WebView2 browser = new();

    public MailWindow(string url, Process? serverProcess)
    {
        server = serverProcess;
        Text = "tfwbmail";
        Icon = SystemIcons.Application;
        StartPosition = FormStartPosition.CenterScreen;
        MinimumSize = new Size(960, 640);
        Width = 1440;
        Height = 900;
        BackColor = Color.FromArgb(15, 23, 42);

        browser.Dock = DockStyle.Fill;
        Controls.Add(browser);
        Shown += async (_, _) =>
        {
            try
            {
                await browser.EnsureCoreWebView2Async();
                browser.CoreWebView2.Settings.AreDefaultContextMenusEnabled = true;
                browser.CoreWebView2.Settings.IsStatusBarEnabled = false;
                browser.CoreWebView2.Navigate(url);
            }
            catch (Exception error)
            {
                MessageBox.Show(this,
                    "WebView2 ontbreekt. Installeer Microsoft Edge WebView2 Runtime en start opnieuw.\n\n" +
                    error.Message, "tfwbmail", MessageBoxButtons.OK, MessageBoxIcon.Error);
                Close();
            }
        };
        FormClosed += (_, _) =>
        {
            if (server is { HasExited: false })
            {
                try { server.Kill(true); } catch { }
            }
        };
    }
}
