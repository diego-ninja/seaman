<p align="center">
    <img  alt="Seaman logo" src="/assets/seaman-logo-github.png"/>
</p>
<h2 align="center" style="border:none !important">
<code>seaman is sail for symfony</code>
</h2>


## Overview
Docker development environment manager for Symfony 7+, inspired by [Laravel Sail](https://github.com/laravel/sail). Seaman provides a sophisticated yet simple way to manage your Symfony development environment with Docker, offering intelligent project detection, service orchestration, and developer-friendly tooling.

## Installation

```bash
curl -sS https://raw.githubusercontent.com/diego-ninja/seaman/main/installer | bash
```

## Quick Start

```bash
# Initialize your Symfony project
seaman init

# Start your environment
seaman start
```

Your application will be available at `http://localhost:8000`

## Install as dependency

```bash
composer require --dev seaman/seaman
vendor/bin/seaman init
```

## Documentation

For complete documentation, visit the [docs](docs/index.md) directory


## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Credits

This project is developed and maintained by 🥷 [Diego Rin](https://diego.ninja) in his free time.

If you find this project useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs and issues
- 💡 Suggesting new features
- 🔧 Contributing code improvements

---

**Made with ❤️ for the PHP community**
