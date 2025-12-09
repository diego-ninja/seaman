# Traefik Opcional y Detección Inteligente de DNS

## Resumen

Dos funcionalidades para mejorar la flexibilidad de Seaman:

1. **Traefik Opcional**: Permitir que el usuario elija si usar Traefik como reverse proxy durante la inicialización, con comandos para activar/desactivar después.

2. **Detección Inteligente de DNS**: Detectar múltiples proveedores DNS disponibles en el sistema y ofrecer recomendación con opción de elegir.

---

## Funcionalidad 1: Traefik Opcional

### Comportamiento

- **Durante init**: Preguntar si usar Traefik (default: Sí)
- **Post-init**: Comandos `proxy:enable` y `proxy:disable` para toggle
- **Sin Traefik**: App y servicios exponen puertos directamente (localhost:80, localhost:8025, etc.)

### Flujo de Inicialización

En `InitializationWizard`, después de servicios adicionales:

```
¿Quieres usar Traefik como reverse proxy?
  • Sí - HTTPS automático, dominios locales (app.proyecto.local)
  • No - Acceso directo por puertos (localhost:80, localhost:8025, etc.)
```

### Generación de Docker Compose

**Con proxy habilitado** (comportamiento actual):
- Servicio Traefik incluido
- Labels de routing en app y servicios
- Acceso vía `https://app.proyecto.local`

**Con proxy deshabilitado**:
- Sin servicio Traefik
- Sin labels de Traefik
- App expone `ports: ["${APP_PORT:-80}:80"]`
- Servicios con UI exponen puertos directos (ya lo hacen)

### Comandos de Toggle

**`seaman proxy:enable`**:
1. Cargar configuración
2. Verificar no esté ya habilitado
3. Actualizar `proxy.enabled = true`
4. Regenerar docker-compose.yml
5. Inicializar Traefik (directorios, config, certificados)
6. Guardar configuración
7. Mensaje: "Proxy enabled. Run 'seaman restart' to apply changes."

**`seaman proxy:disable`**:
1. Cargar configuración
2. Verificar no esté ya deshabilitado
3. Actualizar `proxy.enabled = false`
4. Regenerar docker-compose.yml (sin Traefik)
5. Guardar configuración
6. No eliminar `.seaman/traefik/` ni `.seaman/certs/`
7. Mensaje: "Proxy disabled. Run 'seaman restart' to apply changes."

---

## Funcionalidad 2: Detección Inteligente de DNS

### Proveedores Soportados

| Proveedor | Prioridad | Plataforma | Path de configuración |
|-----------|-----------|------------|----------------------|
| macOS resolver | 1 | Darwin | `/etc/resolver/{project}.local` |
| dnsmasq | 2 | Linux/macOS | `/etc/dnsmasq.d/seaman-{project}.conf` |
| systemd-resolved | 3 | Linux | `/etc/systemd/resolved.conf.d/seaman-{project}.conf` |
| NetworkManager | 4 | Linux | `/etc/NetworkManager/dnsmasq.d/seaman-{project}.conf` |
| Manual | 99 | Todos | N/A |

### Detección

```php
public function detectAvailableProviders(): array
{
    $providers = [];

    if (PHP_OS_FAMILY === 'Darwin') {
        $providers[] = macOS resolver;
    }

    if ($this->hasDnsmasq()) {
        $providers[] = dnsmasq;
    }

    if ($this->hasSystemdResolved()) {
        $providers[] = systemd-resolved;
    }

    if ($this->hasNetworkManager()) {
        $providers[] = NetworkManager;
    }

    // Ordenar por prioridad
    return sorted($providers);
}
```

### Flujo de Configuración

1. Detectar proveedores disponibles
2. Si ninguno: mostrar instrucciones manuales
3. Recomendar el de mayor prioridad
4. Preguntar: "Use {recomendado} for DNS? [Y/n]"
5. Si acepta: aplicar configuración
6. Si rechaza: mostrar select con todas las opciones + manual

### Contenido de Configuración

**dnsmasq**:
```
address=/.{projectName}.local/127.0.0.1
```

**systemd-resolved**:
```ini
[Resolve]
DNS=127.0.0.1
Domains=~{projectName}.local
```

**NetworkManager**:
```
address=/.{projectName}.local/127.0.0.1
```

**macOS resolver**:
```
nameserver 127.0.0.1
```

---

## Archivos a Crear

| Archivo | Descripción |
|---------|-------------|
| `src/Enum/DnsProvider.php` | Enum con proveedores DNS y prioridades |
| `src/ValueObject/DetectedDnsProvider.php` | VO para proveedor detectado |
| `src/Command/ProxyEnableCommand.php` | Comando para habilitar Traefik |
| `src/Command/ProxyDisableCommand.php` | Comando para deshabilitar Traefik |

## Archivos a Modificar

| Archivo | Cambios |
|---------|---------|
| `src/ValueObject/InitializationChoices.php` | Añadir `useProxy: bool` |
| `src/ValueObject/ProxyConfig.php` | Añadir `disabled()` factory |
| `src/ValueObject/DnsConfigurationResult.php` | Añadir `restartCommand` |
| `src/Service/InitializationWizard.php` | Añadir pregunta de proxy |
| `src/Service/ConfigurationFactory.php` | Crear ProxyConfig según elección |
| `src/Service/DockerComposeGenerator.php` | Condicionar generación por proxy |
| `src/Service/ProjectInitializer.php` | Condicionar initializeTraefik() |
| `src/Service/InitializationSummary.php` | Mostrar estado del proxy |
| `src/Service/DnsConfigurationHelper.php` | Nueva lógica de detección |
| `src/Command/InitCommand.php` | Nuevo flujo DNS con selección |
| `src/Template/docker/compose.base.twig` | Condicionar labels y puertos |
| Templates de servicios | Condicionar labels por `proxy_enabled` |
