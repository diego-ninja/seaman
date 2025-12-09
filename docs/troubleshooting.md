# Troubleshooting

Common issues and solutions.

## Docker Issues

### "Cannot connect to Docker daemon"

Docker is not running or not accessible.

**Linux:**
```bash
# Check Docker service
sudo systemctl status docker

# Start Docker
sudo systemctl start docker

# Add yourself to docker group (avoids sudo)
sudo usermod -aG docker $USER
# Log out and back in for this to take effect
```

**macOS:**
- Open Docker Desktop
- Wait for "Docker is running" status

**WSL2:**
- Ensure Docker Desktop is running on Windows
- Check WSL2 backend is enabled in Docker Desktop settings

### "docker compose: command not found"

You have Docker Compose V1 (standalone) instead of V2 (plugin).

**Check version:**
```bash
docker compose version  # V2 (correct)
docker-compose --version  # V1 (old)
```

**Install V2 on Linux:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install docker-compose-plugin

# Or download directly
mkdir -p ~/.docker/cli-plugins
curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 -o ~/.docker/cli-plugins/docker-compose
chmod +x ~/.docker/cli-plugins/docker-compose
```

### Containers won't start

**Check logs:**
```bash
seaman logs app
docker compose logs
```

**Common causes:**
- Port already in use (see below)
- Out of disk space: `docker system df`
- Image build failed: `seaman rebuild`

## Port Conflicts

### "Port is already in use"

Another process is using the port.

**Find what's using a port:**
```bash
# Linux/macOS
lsof -i :3306
ss -tlnp | grep 3306

# See which process
ps aux | grep <PID>
```

**Solutions:**

1. Stop the conflicting process
2. Let Seaman suggest an alternative port (automatic)
3. Change the port in `.seaman/seaman.yaml`:
   ```yaml
   services:
     mysql:
       port: 3307  # Use different port
   ```

### Multiple Seaman projects

Each project needs unique ports. Options:

1. Only run one project at a time
2. Use different ports per project
3. Use Traefik proxy (all projects share port 80/443)

## Platform-Specific Issues

### Linux

**Permission denied on volumes:**
```bash
# Check your user ID
id

# Ensure WWWGROUP in .env matches your group ID
echo "WWWGROUP=$(id -g)" >> .env
seaman rebuild
```

**SELinux issues (Fedora/RHEL):**
```bash
# Add :Z to volume mounts in docker-compose.yml
# Or disable SELinux for Docker (not recommended for production)
```

### macOS

**Slow file sync:**

Docker Desktop's file sharing can be slow. Options:
- Use mutagen or docker-sync
- Enable VirtioFS in Docker Desktop settings
- Reduce watched files in your IDE

**"Too many open files":**
```bash
# Increase limits
ulimit -n 65536
```

### WSL2

**Files created with wrong permissions:**

Add to `/etc/wsl.conf` in your WSL distro:
```ini
[automount]
options = "metadata,umask=22,fmask=11"
```

Then restart WSL: `wsl --shutdown`

**Slow when accessing Windows files:**

Keep your project inside WSL filesystem (`/home/...`), not Windows (`/mnt/c/...`).

## Xdebug Issues

### Xdebug not connecting

**Check it's enabled:**
```bash
seaman xdebug on
seaman php -m | grep xdebug
```

**Check IDE configuration:**
- IDE key matches (default: `PHPSTORM`)
- IDE is listening for connections
- Port 9003 is not blocked

**Docker-specific:**
```bash
# Check host can be reached from container
seaman shell
ping host.docker.internal
```

**Linux additional setup:**

On Linux, `host.docker.internal` may not work. Add to `.seaman/seaman.yaml`:
```yaml
php:
  xdebug:
    client_host: 172.17.0.1  # Docker bridge IP
```

Or use your machine's IP address.

### Xdebug slows everything down

Xdebug has overhead. Best practice:
```bash
seaman xdebug off  # When not debugging
seaman xdebug on   # Only when needed
```

## Database Issues

### Can't connect to database

**From your app (inside container):**
- Host: service name (e.g., `postgresql`, `mysql`)
- Port: internal port (e.g., `5432`, `3306`)

**From host machine:**
- Host: `127.0.0.1` or `localhost`
- Port: mapped port (check `.env`)

**Check database is running:**
```bash
seaman status
seaman logs postgresql
```

### Database data lost

Data persists in Docker volumes. It's lost if you run:
```bash
seaman destroy  # Removes volumes!
```

Use `seaman stop` to preserve data.

**Backup before destroying:**
```bash
seaman db:dump --output=backup.sql
seaman destroy
seaman start
seaman db:restore backup.sql
```

## Build Issues

### "Failed to build image"

**Check Dockerfile syntax:**
```bash
docker build -f .seaman/Dockerfile .
```

**Common causes:**
- Network issues downloading packages
- Invalid PHP version
- Missing base image

**Try rebuilding:**
```bash
seaman rebuild
```

### Out of disk space

Docker can use a lot of disk space.

**Check usage:**
```bash
docker system df
```

**Clean up:**
```bash
# Remove unused containers, networks, images
docker system prune

# Also remove unused volumes (careful!)
docker system prune --volumes

# Remove all Seaman-related images
docker images | grep seaman | awk '{print $3}' | xargs docker rmi
```

## Getting Help

If these don't help:

1. Check existing issues: [GitHub Issues](https://github.com/diego-ninja/seaman/issues)
2. Open a new issue with:
   - Seaman version (`seaman --version`)
   - OS and architecture
   - Docker version (`docker --version`)
   - Error messages
   - Steps to reproduce
