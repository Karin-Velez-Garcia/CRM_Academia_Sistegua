# Colegio Esteca PC — Plataforma de comunicación

Convocatorias por correo para el Colegio Esteca PC (sedes Sanarate, Salamá y Cobán):
reuniones con padres de familia y capacitaciones para catedráticos. Desarrollada solo con Laravel 12 (vistas Blade), sin Angular.

## Requisitos

- PHP 8.2 o superior (por ejemplo `C:\xampp_8\php`)
- Composer
- MySQL 8

## Instalación

```bash
composer install
cp .env.example .env        # y configure DB_* con sus datos de MySQL
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001
```

Abra http://127.0.0.1:8001

Usuario inicial (cambie la contraseña en **Mi perfil** después de ingresar):

- Correo: `admin@colegioesteca.edu.gt`
- Contraseña: `Admin12345`

Se pueden cambiar antes de sembrar la base con `ADMIN_EMAIL` y `ADMIN_PASSWORD` en el `.env`.

## Pruebas

```bash
php artisan test
```

Las pruebas usan la base MySQL `colegio_esteca_test` (créela una vez con
`CREATE DATABASE colegio_esteca_test;`); nunca tocan los datos reales.

## Fase 1 (lista)

- Inicio de sesión con límite de intentos y bloqueo de usuarios inactivos
- Usuarios: crear, editar, activar/desactivar, eliminar, buscar y filtrar
- Roles y permisos por módulo (Spatie Permission). Roles iniciales:
  Administrador (acceso total), Director de sede, Secretaría
- Catálogo de Guatemala: 22 departamentos y 340 municipios con códigos del INE
  (Guastatoya = 0201, Sanarate = 0207). Endpoint para formularios:
  `GET /api/departamentos/{id}/municipios`
- Mi perfil y cambio de contraseña
- Interfaz en español con el diseño de la plantilla (modo claro/oscuro, adaptable a celular)

## Fase 2 (lista)

- Sedes Sanarate, Salamá y Cobán (crear, editar, desactivar)
- Padres de familia (con estudiante y grado/sección) y catedráticos (con curso o área):
  crear, editar, eliminar, buscar, filtrar por sede, grupo o correo, y exportar a Excel
- Grupos (por ejemplo "Padres de 3.º Básico") con acciones en bloque para agregar o quitar miembros
- Carga desde Excel con plantilla descargable: valida cada fila, detecta repetidos, reconoce la sede
  sin tildes, crea los grupos que falten y actualiza a las personas ya registradas.
  Archivo de prueba: `ejemplos/padres_ejemplo.xlsx` (trae 4 filas con errores a propósito)
- Usuarios con sede asignada: solo ven y manejan los contactos de su sede

## Fase 3 (lista)

- Reuniones (para padres de familia) y capacitaciones (para catedráticos), presenciales o virtuales
- Presencial: el lugar se completa con la dirección de la sede (obligatoria en cada sede)
- Virtual: enlace de Zoom, Google Meet o Teams (se reconoce la plataforma)
- Dirigidas a todos los padres/catedráticos de la sede o a grupos; muestra cuántos tienen correo
- Capacitaciones con facilitador y cupo máximo
- Próximos, realizados y cancelados; cancelar con motivo, reactivar, duplicar y eliminar
- Archivo de calendario (.ics) para Google Calendar, Outlook o el celular

## Fase 4 (lista)

- Invitaciones por correo desde la ficha de cada evento, con plantillas y variables ({nombre}, {fecha}…)
- Página pública para confirmar o rechazar (con comentario), agregar al calendario o darse de baja
- Seguimiento: vistas, confirmadas, no asistirán, sin respuesta, errores; respuesta manual y reenvío
- Recordatorio a quienes no respondieron; aviso de cambio y de cancelación
- Historial de envíos y módulo de plantillas

## Fase 5 (lista)

- Asistencia por evento: lista con todos los destinatarios (con o sin correo), agregar a quien llegó sin invitación,
  lista para firmas en PDF y exportación a Excel
- Constancias en PDF para catedráticos que asistieron a capacitaciones, con código de verificación público
  (`/constancia/verificar/{codigo}`) y envío por correo
- Reportes por evento y por persona, con filtros de fechas, sede y tipo, exportables a Excel
- Historial de participación en la ficha de cada padre o catedrático

## Recordatorios y QR

- Botón **Enviar recordatorio** en la ficha del evento: a quienes no han respondido y/o a quienes confirmaron,
  cada grupo con su texto (se recomienda enviarlo el día anterior)
- **QR del evento** para proyectar o imprimir: cada asistente lo escanea y se registra con su correo o DPI
- **QR personal** (pase de entrada) en cada invitación: el personal lo escanea en la entrada
- Panel **Control del evento**: enviadas vs confirmadas vs no confirmadas vs asistencias vs inasistencias

## Correo

Modo de prueba (actual): `MAIL_MAILER=log` y `MAIL_LOG_CHANNEL=correos` guardan los correos en
`storage/logs/correos.log` sin enviarlos. Use "Vista previa" en el evento para ver cómo quedan.
Para producción use un servicio de envío masivo (Brevo, Amazon SES o Mailgun) en lugar de Gmail,
que limita a unos 500 correos por día.
