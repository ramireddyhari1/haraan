param(
    [string]$MarkdownPath = "reports\actionboard-how-it-works.md",
    [string]$PdfPath = "reports\actionboard-how-it-works.pdf"
)

function Has-Command($name) {
    return (Get-Command $name -ErrorAction SilentlyContinue) -ne $null
}

if (-not (Test-Path $MarkdownPath)) {
    Write-Error "Markdown file not found: $MarkdownPath"
    exit 2
}

if (Has-Command "pandoc") {
    if (Has-Command "xelatex") {
        pandoc $MarkdownPath -o $PdfPath --pdf-engine=xelatex
        exit $LASTEXITCODE
    }

    if (Has-Command "wkhtmltopdf") {
        $tmpHtml = [IO.Path]::GetTempFileName() + ".html"
        pandoc $MarkdownPath -o $tmpHtml
        wkhtmltopdf $tmpHtml $PdfPath
        Remove-Item $tmpHtml -ErrorAction SilentlyContinue
        exit $LASTEXITCODE
    }

    Write-Host "Pandoc is installed but no supported PDF engine found (xelatex or wkhtmltopdf)."
    Write-Host "Install a LaTeX distribution (for xelatex) or wkhtmltopdf, then re-run this script."
    exit 3
} else {
    Write-Host "Pandoc is not installed. Install it from https://pandoc.org/ or via package manager:"
    Write-Host "  choco install pandoc    (Chocolatey)"
    Write-Host "  winget install Pandoc.Pandoc    (winget)"
    Write-Host "Alternatives: open the Markdown in VS Code and use 'Export to PDF' or use an online converter."
    exit 1
}
