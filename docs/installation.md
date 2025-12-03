# Installation

## Global Installation (Recommended)

Install Seaman globally using the installer script:

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash
```

This installs the `seaman` command globally at `~/.seaman/seaman.phar`, making it available from anywhere in your system.

The installer script:
- Creates `~/.seaman` directory
- Downloads the latest PHAR release
- Makes it executable
- Adds a wrapper script to your PATH

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

The installer script checks for updates automatically. To manually update:

```bash
rm ~/.seaman/seaman.phar
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash
```

### Composer Installation

```bash
composer update seaman/seaman
```

## Uninstalling

### Global Installation

```bash
rm -rf ~/.seaman
# Remove wrapper script if you created one
```

### Composer Installation

```bash
composer remove seaman/seaman
```
