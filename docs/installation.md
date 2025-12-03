# Installation

## Global Installation (Recommended)

Install Seaman globally using the installer script:

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/refs/heads/main/installer | bash
```

The installer will:

1. **Download** the latest Seaman PHAR from GitHub releases
2. **Install** to one of these locations (in order of preference):
   - `/usr/local/bin/seaman` (system-wide, requires write permission or sudo)
   - `~/.local/bin/seaman` (user installation, no sudo required)
3. **Configure PATH** automatically if installing to `~/.local/bin`
4. **Verify** the installation and display version

After installation, the `seaman` command will be available globally:

```bash
seaman --version
seaman init
```

## Composer Dev Dependency

Install Seaman as a development dependency in your project:

```bash
composer require --dev seaman/seaman
```

Then use it via:

```bash
vendor/bin/seaman init
vendor/bin/seaman start
```

This approach is useful when:
- You want to version-lock Seaman with your project
- Different projects need different Seaman versions
- You want to commit the Seaman version to your repository

## Manual Installation

Download the PHAR directly:

```bash
mkdir -p ~/.seaman
curl -sS -L https://github.com/diego-ninja/seaman/releases/latest/download/seaman.phar -o ~/.seaman/seaman.phar
chmod +x ~/.seaman/seaman.phar
```

Create a wrapper script (optional):

```bash
#!/usr/bin/env bash
php ~/.seaman/seaman.phar "$@"
```

## Verifying Installation

Check that Seaman is installed correctly:

```bash
seaman --version
```

You should see output like:

```
🔱 Seaman 1.0.0-beta
```

## Updating

### Global Installation

Simply run the installer again to update to the latest version:

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/refs/heads/main/installer | bash
```

This will download and install the latest release, replacing your current installation.

### Composer Installation

```bash
composer update seaman/seaman
```

## Uninstalling

### Global Installation

Remove the seaman binary from your system:

```bash
# If installed to /usr/local/bin (may require sudo)
sudo rm /usr/local/bin/seaman

# If installed to ~/.local/bin
rm ~/.local/bin/seaman
```

If you added `~/.local/bin` to your PATH, you can remove it from your shell configuration file (`~/.bashrc`, `~/.zshrc`, etc.).

### Composer Installation

```bash
composer remove seaman/seaman
```
