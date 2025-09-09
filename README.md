# TaxiClass - Backend API

Sistema de reservas de taxis con integración API Auriga - Backend PHP

## 🚀 Tecnologías

- **PHP 8.0+**
- **MySQL** para base de datos
- **JWT** para autenticación (Firebase PHP-JWT)
- **PHPMailer** para envío de emails
- **XAMPP** como servidor de desarrollo
- **Composer** para gestión de dependencias

## 📋 Requisitos Previos

- PHP 8.0 o superior
- MySQL 5.7+
- XAMPP o servidor web con PHP
- Composer (instalado localmente en el proyecto)
- Certificados SSL actualizados (producción)

## 🔧 Instalación

1. Clonar el repositorio en la carpeta htdocs de XAMPP:
```bash
cd C:\xampp\htdocs
git clone [url-del-repo] reservas-taxiclass
cd reservas-taxiclass/backend
```

2. Instalar dependencias (Composer está incluido localmente):
```bash
php composer.phar install
```

3. Crear base de datos:
- Acceder a phpMyAdmin
- Crear base de datos `taxiclass_db`
- Importar archivo SQL desde `/setup/database.sql` (si existe)

4. Configurar archivo `.env`:
```env
# Base de datos
DB_HOST=localhost
DB_DATABASE=taxiclass_db
DB_USERNAME=root
DB_PASSWORD=
DB_PORT=3306

# JWT
JWT_SECRET=tu_clave_secreta_aqui
JWT_EXPIRE=3600

# Entorno
APP_ENV=development
APP_DEBUG=true

# API de Auriga
CLIENT_ID_AURIGA=tu_client_id
CLIENT_KEY_AURIGA=tu_client_key
API_URL_AURIGA=url_api_auriga

# Email
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_email@gmail.com
MAIL_FROM_NAME=TaxiClass
ADMIN_RESERVATION_EMAIL=email_admin@example.com

# Google Maps (para validaciones)
GOOGLE_MAPS_API_KEY=tu_api_key
```

## 📁 Estructura del Proyecto

```
backend/
├── api/
│   ├── config/         # Configuración DB y entorno
│   ├── controllers/    # Controladores de endpoints
│   ├── models/         # Modelos de datos
│   ├── services/       # Servicios (Email, etc)
│   ├── helpers/        # Helpers (JWT, etc)
│   └── middleware/     # Middleware de autenticación
├── vendor/             # Dependencias (JWT, PHPMailer)
├── setup/              # Scripts de instalación
├── .env                # Variables de entorno
├── .htaccess           # Configuración Apache
├── index.php           # Router principal
└── composer.json       # Dependencias del proyecto
```

## 🔌 API Endpoints

### Autenticación
- `POST /auth/login` - Iniciar sesión
- `POST /auth/verify-2fa` - Verificar código 2FA
- `POST /auth/logout` - Cerrar sesión
- `GET /auth/me` - Obtener usuario actual
- `GET /auth/verify` - Verificar token
- `POST /auth/refresh` - Refrescar token
- `POST /auth/enable-2fa` - Activar 2FA
- `POST /auth/confirm-enable-2fa` - Confirmar activación 2FA
- `POST /auth/disable-2fa` - Desactivar 2FA
- `PUT /auth/update-profile` - Actualizar perfil
- `POST /auth/change-password` - Cambiar contraseña

### Actividades
- `GET /activities` - Obtener actividades del usuario

### Localizaciones
- `GET /locations` - Listar lugares predefinidos
- `GET /locations/search` - Buscar lugares

### Reservas
- `POST /reservations/create` - Crear nueva reserva
- `GET /reservations/list` - Listar reservas del usuario
- `GET /reservations/detail/{id}` - Detalle de reserva
- `POST /reservations/cancel/{id}` - Cancelar reserva
- `GET /reservations/receipt/{id}` - Descargar comprobante

## 📊 Base de Datos

### Tablas principales:
- `users` - Usuarios del sistema
- `user_sessions` - Sesiones activas
- `two_factor_attempts` - Intentos 2FA
- `password_resets` - Reseteo de contraseñas
- `activity_logs` - Log de actividades
- `reservation_logs` - Reservas guardadas
- `predefined_locations` - Lugares frecuentes

## 🔐 Seguridad

- Autenticación JWT con tokens Bearer
- Sistema 2FA por email
- Validación de signatures SHA1 para API Auriga
- CORS configurado para origen específico
- Prepared statements para prevenir SQL injection
- Logs de actividad para auditoría

## 📧 Sistema de Emails

- **2FA**: Envío de códigos de verificación
- **Confirmación de reserva**: Email al usuario con detalles
- **Notificación admin**: Email a administración por cada reserva
- Plantillas HTML responsive
- Configuración SSL automática según entorno

## 🔗 Integración API Auriga

### Headers requeridos:
- Crear reserva: `X-Authorization` (mayúsculas)
- Cancelar reserva: `x-authorization` (minúsculas)
- Accept: `*/*`

### Formato de fecha:
- ISO8601 sin dos puntos en timezone: `2025-09-18T17:00:00+0200`

### Preferences disponibles:
- 1662: Alzador infantil
- 1663: Vehículo 5-6 plazas
- 1665: Vehículo 7 plazas
- 1666: Recogida en aeropuerto

## 🛠️ Configuraciones Especiales

- Composer instalado localmente (problemas SSL)
- JWT instalado manualmente sin Composer
- Autoload personalizado en `/vendor/autoload.php`
- En desarrollo: SSL deshabilitado para emails

## 📝 Notas de Desarrollo

- El código 2FA se muestra en logs del servidor (solo desarrollo)
- Campo `debug_code` debe eliminarse en producción
- CustomerEmail causa error 400 en Auriga (bug conocido)
- Todos los campos de dirección deben venir de Google Maps

## 🚦 Estados de Reserva

- `active` - Reserva activa
- `cancelled` - Reserva cancelada
- `completed` - Reserva completada

## 🐛 Solución de Problemas

### Error SSL en emails
- Verificar variable `APP_ENV=development` en `.env`
- En producción, actualizar certificados cacert.pem

### Error 400 Auriga
- Verificar formato de fecha (sin : en timezone)
- Verificar orden exacto de campos en signature
- CustomerEmail debe ser null

## 📞 Soporte

Para soporte técnico o preguntas sobre el backend, contactar al equipo de desarrollo.
