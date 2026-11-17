<#
    database/dobih/export-dobih.ps1: export the DoBIH Access database to CSV files.

    The live host can't read an Access file, so this runs on a Windows machine with the
    Microsoft Access Database Engine (ACE) installed. It writes one UTF-8 CSV per table, which
    `php artisan dobih:import` then reads.

        powershell -ExecutionPolicy Bypass -File database\dobih\export-dobih.ps1 `
            -Database C:\path\to\DoBIH_v18_6.mdb -OutDir storage\app\dobih

    The database is opened read-only. Download it from https://www.hill-bagging.co.uk/dobih/downloads
    (licensed CC BY 4.0, see config/dobih.php).
#>

param(
    [Parameter(Mandatory = $true)][string]$Database,
    [string]$OutDir
)

$ErrorActionPreference = 'Stop'

if (-not $OutDir) {
    $projectRoot = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
    $OutDir = Join-Path $projectRoot 'storage\app\dobih'
}

if (-not (Test-Path $Database)) { throw "Database not found: $Database" }
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

$tables = 'Hills', 'Hillnames', 'Areas', 'Arealink', 'Class', 'Classlink', 'Countries', 'Maps', 'Mapslink'
$invariant = [System.Globalization.CultureInfo]::InvariantCulture
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Format-Cell($value) {
    if ($value -is [System.DBNull] -or $null -eq $value) { return '' }
    if ($value -is [bool]) { if ($value) { return '1' } else { return '0' } }
    if ($value -is [double] -or $value -is [single] -or $value -is [decimal]) { return ([System.Convert]::ToString($value, $invariant)) }
    if ($value -is [datetime]) { return $value.ToString('yyyy-MM-dd HH:mm:ss', $invariant) }
    $text = [string]$value
    if ($text -match '[",\r\n]') { return '"' + $text.Replace('"', '""') + '"' }
    return $text
}

$cn = New-Object System.Data.OleDb.OleDbConnection("Provider=Microsoft.ACE.OLEDB.16.0;Data Source=$Database;Mode=Read")
$cn.Open()

try {
    foreach ($table in $tables) {
        $cmd = $cn.CreateCommand()
        $cmd.CommandText = "SELECT * FROM [$table]"
        $reader = $cmd.ExecuteReader()
        $path = Join-Path $OutDir "$table.csv"
        $writer = New-Object System.IO.StreamWriter($path, $false, $utf8)
        try {
            $names = for ($i = 0; $i -lt $reader.FieldCount; $i++) { Format-Cell $reader.GetName($i) }
            $writer.Write(($names -join ',') + "`n")
            $rows = 0
            while ($reader.Read()) {
                $cells = for ($i = 0; $i -lt $reader.FieldCount; $i++) { Format-Cell $reader.GetValue($i) }
                $writer.Write(($cells -join ',') + "`n")
                $rows++
            }
        }
        finally { $writer.Dispose(); $reader.Dispose() }
        '{0,-12} {1,7} rows -> {2}' -f $table, $rows, $path
    }
}
finally { $cn.Close() }
