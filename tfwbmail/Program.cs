using System.Diagnostics;
using System.Drawing;
using System.Net;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
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

        var serverReady = await IsServerReady(Url);
        var php = FindPhp();
        var missingPhp = !serverReady && php is null;
        var missingWebView = !HasWebView2();
        if (missingPhp || missingWebView)
        {
            using var installer = new InstallationWizard(missingPhp, missingWebView);
            if (installer.ShowDialog() != DialogResult.OK)
            {
                return;
            }

            php = FindPhp();
            if ((missingPhp && php is null) || (missingWebView && !HasWebView2()))
            {
                ShowError("De benodigde onderdelen zijn niet geïnstalleerd. tfwbmail wordt afgesloten.");
                return;
            }
        }

        if (serverReady)
        {
            Application.Run(new MailWindow(Url, null));
            return;
        }

        if (php is null)
        {
            ShowError("PHP met IMAP en SQLite is niet beschikbaar.");
            return;
        }

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

    internal static string? FindPhp()
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

    internal static bool InstallPackage(string packageId)
    {
        var winget = FindOnPath("winget.exe");
        if (winget is null) return false;

        try
        {
            using var install = Process.Start(new ProcessStartInfo
            {
                FileName = winget,
                Arguments = $"install --id {packageId} --exact " +
                            "--accept-source-agreements --accept-package-agreements",
                UseShellExecute = false,
                CreateNoWindow = false
            });
            if (install is null || !install.WaitForExit((int)TimeSpan.FromMinutes(10).TotalMilliseconds))
            {
                return false;
            }
            return install.ExitCode == 0;
        }
        catch
        {
            return false;
        }
    }

    internal static bool HasWebView2()
    {
        try
        {
            return !string.IsNullOrWhiteSpace(
                CoreWebView2Environment.GetAvailableBrowserVersionString());
        }
        catch
        {
            return false;
        }
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

internal sealed class InstallationWizard : Form
{
    private readonly bool needsPhp;
    private readonly bool needsWebView;
    private readonly Label phpStatus = new();
    private readonly Label webViewStatus = new();
    private readonly Button installButton = new();
    private readonly Button cancelButton = new();

    public InstallationWizard(bool missingPhp, bool missingWebView)
    {
        needsPhp = missingPhp;
        needsWebView = missingWebView;
        Text = "tfwbmail installeren";
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        ClientSize = new Size(620, missingPhp && missingWebView ? 330 : 280);
        BackColor = Color.White;

        var title = new Label
        {
            Text = "tfwbmail heeft eenmalige onderdelen nodig",
            Font = new Font("Segoe UI", 16, FontStyle.Bold),
            AutoSize = true,
            Location = new Point(28, 24)
        };
        var explanation = new Label
        {
            Text = "Er wordt eerst gecontroleerd wat al aanwezig is. " +
                   "Klik alleen op Installeren als je toestemming geeft.",
            AutoSize = false,
            Size = new Size(560, 42),
            Location = new Point(30, 65)
        };
        Controls.Add(title);
        Controls.Add(explanation);

        var y = 125;
        if (needsPhp)
        {
            AddComponent("PHP 8.3 met IMAP en SQLite", "PHP.PHP.8.3", phpStatus, ref y);
        }
        else
        {
            phpStatus.Text = "PHP met IMAP en SQLite: al geïnstalleerd";
        }

        if (needsWebView)
        {
            AddComponent("Microsoft Edge WebView2 Runtime", "Microsoft.EdgeWebView2Runtime",
                webViewStatus, ref y);
        }

        installButton.Text = "Installeren";
        installButton.Size = new Size(120, 34);
        installButton.Location = new Point(350, ClientSize.Height - 52);
        installButton.Click += (_, _) => InstallMissingComponents();
        cancelButton.Text = "Annuleren";
        cancelButton.Size = new Size(120, 34);
        cancelButton.Location = new Point(485, ClientSize.Height - 52);
        cancelButton.Click += (_, _) => DialogResult = DialogResult.Cancel;
        Controls.Add(installButton);
        Controls.Add(cancelButton);
        UpdateStatus();
    }

    private void AddComponent(string name, string packageId, Label status, ref int y)
    {
        var label = new Label
        {
            Text = name,
            AutoSize = true,
            Font = new Font("Segoe UI", 10, FontStyle.Bold),
            Location = new Point(32, y)
        };
        status.Text = "Niet gevonden";
        status.AutoSize = true;
        status.ForeColor = Color.DarkOrange;
        status.Location = new Point(390, y);
        status.Tag = packageId;
        Controls.Add(label);
        Controls.Add(status);
        y += 52;
    }

    private void InstallMissingComponents()
    {
        installButton.Enabled = false;
        cancelButton.Enabled = false;
        if (needsPhp && Program.FindPhp() is null)
        {
            phpStatus.Text = "Installatie bezig...";
            phpStatus.ForeColor = Color.DarkBlue;
            Program.InstallPackage((string)phpStatus.Tag!);
        }

        if (needsWebView && !Program.HasWebView2())
        {
            webViewStatus.Text = "Installatie bezig...";
            webViewStatus.ForeColor = Color.DarkBlue;
            Program.InstallPackage((string)webViewStatus.Tag!);
        }

        UpdateStatus();
        if (IsReady())
        {
            DialogResult = DialogResult.OK;
        }
        else
        {
            cancelButton.Enabled = true;
            installButton.Enabled = true;
            MessageBox.Show(this,
                "Een of meer onderdelen zijn niet geïnstalleerd. " +
                "Controleer de melding van WinGet en probeer opnieuw.",
                "tfwbmail", MessageBoxButtons.OK, MessageBoxIcon.Warning);
        }
    }

    private void UpdateStatus()
    {
        if (needsPhp && Program.FindPhp() is not null)
        {
            phpStatus.Text = "Al geïnstalleerd";
            phpStatus.ForeColor = Color.DarkGreen;
        }
        if (needsWebView && Program.HasWebView2())
        {
            webViewStatus.Text = "Al geïnstalleerd";
            webViewStatus.ForeColor = Color.DarkGreen;
        }
    }

    private bool IsReady()
    {
        return (!needsPhp || Program.FindPhp() is not null) &&
               (!needsWebView || Program.HasWebView2());
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
