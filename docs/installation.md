# Installation

## Requirements

Before installing Seaman, ensure you have:

- **Docker Engine 20.10+** or **Docker Desktop 4.0+**
- **Docker Compose V2** (included with Docker Desktop, or install separately on Linux)

Verify Docker is working:

```bash
docker --version
docker compose version
```

## Platform Support

| Platform | Architecture | Status | Notes |
|----------|--------------|--------|-------|
| Linux | x86_64 | Tested | Ubuntu, Debian, Fedora, Arch |
| Linux | arm64 | Tested | Raspberry Pi 4+, AWS Graviton |
| macOS | Apple Silicon | Tested | M1, M2, M3 |
| macOS | Intel | Should work | Not actively tested |
| Windows | WSL2 | Should work | Use Linux instructions inside WSL |
| Windows | Native | Not supported | Use WSL2 instead |

## Global Installation (Recommended)

Install Seaman system-wide using the installer script:

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash
```

The installer will:

1. Download the latest Seaman PHAR from GitHub releases
2. Install to `/usr/local/bin/seaman` (if writable) or `~/.local/bin/seaman`
3. Add `~/.local/bin` to PATH if needed
4. Verify the installation

After installation:

```bash
seaman --version
```

### Linux Notes

On some distributions, you may need to:

```bash
# Ensure ~/.local/bin exists and is in PATH
mkdir -p ~/.local/bin
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

### macOS Notes

If using Homebrew's bash or zsh, the PATH should work automatically. For system bash:

```bash
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

### WSL2 Notes

Inside your WSL2 distribution, follow the Linux instructions. Ensure Docker Desktop is configured to use WSL2 backend.

## Composer Installation

Install Seaman as a development dependency in your project:

```bash
composer require --dev seaman/seaman
```

Use via:

```bash
vendor/bin/seaman init
vendor/bin/seaman start
```

This is useful when:

- Different projects need different Seaman versions
- You want to lock the Seaman version with your project
- You prefer not to install global tools

## Manual Installation

Download the PHAR directly:

```bash
# Download
curl -sL https://github.com/diego-ninja/seaman/releases/latest/download/seaman.phar -o seaman.phar

# Make executable
chmod +x seaman.phar

# Move to PATH (choose one)
sudo mv seaman.phar /usr/local/bin/seaman
# or
mv seaman.phar ~/.local/bin/seaman
```

## Updating

### Global Installation

Run the installer again:

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash
```

### Composer Installation

```bash
composer update seaman/seaman
```

## Uninstalling

### Global Installation

```bash
# If installed to /usr/local/bin
sudo rm /usr/local/bin/seaman

# If installed to ~/.local/bin
rm ~/.local/bin/seaman
```

### Composer Installation

```bash
composer remove seaman/seaman
```

## Verifying Installation

```bash
# Check version
seaman --version

# Should output something like:
# Seaman 1.0.0
```

## Next Steps

- [Getting Started](getting-started.md) — Initialize your first project
- [Commands](commands.md) — Learn available commands
