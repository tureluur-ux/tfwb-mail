using System.Diagnostics;
using System.Runtime.InteropServices;

const string host = "127.0.0.1";
const int port = 8090;
var php = FindPhp();
var baseDirectory = AppContext.BaseDirectory.TrimEnd('\\', '/');
var webRoot = Path.GetFileName(baseDirectory).Equals("downloads", StringComparison.OrdinalIgnoreCase)
    ? Directory.GetParent(baseDirectory)?.FullName ?? baseDirectory
    : baseDirectory;

if (php is null)
{
    MessageBox(IntPtr.Zero,
        "PHP is niet gevonden. Installeer PHP 8.3 of tfwbmail opnieuw.",
        "tfwbmail", 0x10);
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
    $"-S {host}:{port}";

var server = new Process
{
    StartInfo = new ProcessStartInfo
    {
        FileName = php,
        Arguments = arguments,
        WorkingDirectory = webRoot,
        UseShellExecute = false,
        CreateNoWindow = true
    }
};

server.Start();
Thread.Sleep(600);
Process.Start(new ProcessStartInfo
{
    FileName = $"http://{host}:{port}",
    UseShellExecute = true
});

Console.WriteLine("tfwbmail draait op http://127.0.0.1:8090");
Console.WriteLine("Sluit dit venster om de server te stoppen.");
server.WaitForExit();

static string? FindPhp()
{
    var packageRoot = Environment.ExpandEnvironmentVariables(
        @"%LOCALAPPDATA%\Microsoft\WinGet\Packages");
    if (Directory.Exists(packageRoot))
    {
        var phpWithImap = Directory.EnumerateFiles(packageRoot, "php.exe", SearchOption.AllDirectories)
            .FirstOrDefault(file =>
            {
                var ext = Path.Combine(Path.GetDirectoryName(file) ?? "", "ext");
                return File.Exists(Path.Combine(ext, "php_imap.dll"));
            });
        if (phpWithImap is not null) return phpWithImap;
    }

    var links = Environment.ExpandEnvironmentVariables(
        @"%LOCALAPPDATA%\Microsoft\WinGet\Links\php.exe");
    if (File.Exists(links)) return links;

    var candidates = new[] {
        @"C:\php\php.exe",
        @"C:\Program Files\PHP\php.exe",
        @"C:\xampp\php\php.exe"
    };
    return candidates.FirstOrDefault(File.Exists);
}

[DllImport("user32.dll", CharSet = CharSet.Unicode)]
static extern int MessageBox(IntPtr hWnd, string text, string caption, uint type);
