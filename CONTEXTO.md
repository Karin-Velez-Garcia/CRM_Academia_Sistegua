# Contexto del proyecto — Colegio Esteca PC

Documento vivo: se actualiza al terminar cada avance. Resume qué hace el sistema, cómo está
construido, qué está listo y qué falta.

## 1. Qué es

Plataforma web del **Colegio Esteca PC** para organizar y controlar:

- **Reuniones con padres de familia**
- **Capacitaciones para catedráticos**

desde la **invitación por correo** hasta la **confirmación y la asistencia**.

El colegio tiene tres **sedes**: **Sanarate** (El Progreso), **Salamá** (Baja Verapaz) y **Cobán** (Alta Verapaz).

## 2. Tecnología y cómo levantarlo

| Tema | Detalle |
|---|---|
| Carpeta | `colegio_esteca/` (antes `capacitaciones_el_progreso/`, renombrada el 2026-09-25) |
| Repositorio | https://github.com/LusvinMtz/CRM_Demo_esteca (rama `main`) |
| Backend y vistas | Laravel 12 + Blade (sin Angular, sin Node) |
| Diseño | CSS de la plantilla Metronic compilado en `public/assets/css/style.bundle.css`, íconos Keenicons, Bootstrap 5 JS |
| Base de datos | MySQL 8, base `colegio_esteca` (usuario root, contraseña en `.env`; la base anterior `capacitaciones_el_progreso` se conserva como respaldo) |
| Pruebas | MySQL, base `colegio_esteca_test` — `php artisan test` |
| Paquetes | spatie/laravel-permission (roles), maatwebsite/excel (Excel), laravel-lang (español) |
| PHP | 8.2 (`C:\xampp_8\php`) |

Levantar: `php artisan serve --port=8001` → http://127.0.0.1:8001
Usuario inicial: `admin@colegioesteca.edu.gt` / `Admin12345` (cambiarla en Mi perfil).

Datos del colegio (nombre, sedes, lema) centralizados en `config/colegio.php`.
Logo: `public/assets/media/logos/esteca-logo.png` (completo) y `esteca-icon.png` (ícono "CE").

## 3. Estado por fases

| Fase | Contenido | Estado |
|---|---|---|
| 1 | Acceso, usuarios, roles y permisos, catálogo de Guatemala (22 departamentos, 340 municipios INE) | ✅ Lista |
| 2 | Sedes, padres de familia, catedráticos, grupos, carga y exportación Excel | ✅ Lista |
| 3 | Reuniones y capacitaciones, presenciales o virtuales | ✅ Lista |
| 4 | Invitaciones por correo, confirmación de asistencia, recordatorios, plantillas | ✅ Lista (correo en modo de prueba) |
| 5 | Control de asistencia, reportes Excel/PDF, constancias para catedráticos | ✅ Lista |

Pruebas automáticas: **69** (Fase1Test 12, Fase2Test 14, Fase3Test 8, Fase4Test 12, Fase5Test 9, RecordatorioAutomaticoTest 6, QrAsistenciaTest 7, TableroTest 1), todas pasan.

**Datos de demostración:** además de `GeografiaSeeder`, `SedesSeeder`, `RolesPermisosSeeder` y
`PlantillasSeeder`, `DatabaseSeeder` corre `PadresCatedraticosSeeder` (≈410 padres y 15
catedráticos, con apellidos típicos de cada región: comunes en Sanarate, achí en Salamá,
q'eqchi' en Cobán), `GruposDemoSeeder` (grupos por sede y nivel) y `EventosDemoSeeder` (25
eventos entre los últimos 11 meses y las próximas semanas, con ≈1,500 invitaciones y
asistencia simulada de forma realista). Se usa para demos comerciales;
`php artisan migrate:fresh --seed` regenera todo.

Última actualización: 2026-09-27.

### Fase 1 — Base
- Inicio de sesión con límite de 5 intentos; usuarios inactivos no entran y se les cierra la sesión.
- Usuarios: CRUD, activar/desactivar, protección para no quedarse sin administrador.
- Roles: **Administrador** (todo, vía `Gate::before`), **Director de sede**, **Secretaría**. Permisos por módulo en `database/seeders/RolesPermisosSeeder.php`.
- Un usuario con `sede_id` (y que no sea Administrador) solo ve lo de su sede: `User::sedeRestringida()`.

### Fase 2 — Contactos
- Tabla única `contactos` con `tipo` = `padre` | `catedratico`. Padres: estudiante y grado/sección. Catedráticos: curso o área.
- Correo y DPI únicos por tipo. `acepta_correos` permite darse de baja. `token` (uuid) por contacto.
- Grupos (`padre`, `catedratico` o `mixto`; de una sede o de todas) con acciones masivas.
- Importación Excel: `app/Services/ImportadorContactos.php` (valida por fila, repetidos, sede sin tildes, crea grupos). Plantilla y exportación: `app/Services/ExcelContactos.php`. Ejemplo: `ejemplos/padres_ejemplo.xlsx`.

### Fase 3 — Eventos
- Tabla `eventos` con `tipo` = `reunion` | `capacitacion`, `modalidad` = `presencial` | `virtual`.
- Presencial: `lugar` (se propone la dirección de la sede, que es obligatoria). Virtual: `enlace` (se detecta Zoom/Meet/Teams).
- Dirigido a `para_todos` (todo el público de la sede) o a grupos (`evento_grupo`); `Evento::destinatarios()` solo toma contactos de la sede del evento.
- Capacitaciones: facilitador y cupo. Estados: programado, en curso, realizado, cancelado (con motivo). Duplicar y archivo `.ics`.

### Fase 4 — Invitaciones
- Tablas: `invitaciones` (una por persona y evento: envío, vista, respuesta, comentario, recordatorio), `envios` (historial de lotes), `plantillas`.
- Servicio `app/Services/InvitacionesEvento.php`: `invitar()` (solo a quien no tiene invitación), `recordar()` (sin respuesta), `avisar('cambio'|'cancelacion')` (no a quienes dijeron que no), `reenviar()`.
- Correo `app/Mail/CorreoEvento.php` + vistas `resources/views/correos/evento*.blade.php`: logo incrustado, mensaje de la plantilla, datos del evento, botones, adjunto `.ics`, enlace de baja.
- Envío por cola: `app/Jobs/EnviarCorreoEvento.php` (3 intentos; el error queda en la invitación sin detener el resto).
- Página pública del invitado `/invitacion/{token}` (`RespuestaController`, vistas `publico/`): ver detalles, confirmar o rechazar con comentario, agregar al calendario, darse de baja. Límite de 30 peticiones/minuto.
- En la ficha del evento: resumen (invitados, vistos, confirmaron, no asistirán, sin respuesta), pestañas, búsqueda, respuesta manual, reenvío, vista previa y historial.
- Menú "Invitaciones" (historial global) y "Plantillas" (CRUD, una predeterminada por tipo de evento, variables `{nombre}`, `{fecha}`, etc.).
- Al editar un evento con invitaciones: casilla para avisar del cambio (solo si cambió fecha, hora, modalidad, lugar o enlace). Al cancelar: casilla para avisar con el motivo.

### Fase 5 — Asistencia, reportes y constancias
- La asistencia vive en `invitaciones` (`asistio`, `asistencia_at`, `asistencia_por`). Quien no tiene correo o llega sin invitación queda como invitación `no_enviada` (no cuenta en las estadísticas de envío, sí en la asistencia).
- `AsistenciaController`: lista = destinatarios del evento + quienes ya tienen registro; se guarda en bloque; buscar y agregar a quien llegó sin invitación (solo de la sede del evento); lista para firmas en PDF y Excel. Solo desde el día del evento y nunca en cancelados. Permiso nuevo `eventos.asistencia`.
- Constancias (`ConstanciaController`, `resources/views/pdf/constancia.blade.php`): solo capacitaciones y solo asistentes; PDF individual, todas en un PDF, envío por correo (`ConstanciaCorreo`), código único `XXXX-XXXX` y página pública `/constancia/verificar/{codigo}`.
- Reportes (`ReporteController`, menú "Reportes"): por evento (invitados, confirmaron, no asistirán, asistieron, %; totales por sede) y por persona (convocatorias, confirmó, asistió, faltó; primero quien menos asiste). Filtros de fechas (por defecto el año en curso), sede y tipo; exportación Excel con `App\Services\ExcelTabla`.
- Ficha del contacto: historial de participación. Ficha del evento: botón "Asistencia" y resumen de asistentes.
- PDF con barryvdh/laravel-dompdf (fuente DejaVu Sans para las tildes).
- `RolesPermisosSeeder` ahora solo agrega permisos nuevos a roles existentes (no pisa lo configurado en la aplicación).

### Recordatorio (botón en la ficha del evento)
- **Decisión del usuario:** el recordatorio se envía con el botón **"Enviar recordatorio"** de la ficha del evento (no con un `.bat` ni ventanas abiertas). Se eliminó `iniciar.bat`.
- En el cuadro se elige a quién: **quienes no han respondido** (se les pide confirmar) y/o **quienes confirmaron** ("le esperamos"), cada grupo con su asunto y mensaje editables y vista previa. Textos por defecto en `InvitacionesEvento::TEXTOS_AUTOMATICOS`. Nunca va a quienes dijeron que no ni a quienes se dieron de baja.
- `InvitacionesEvento::recordar($textos, $usuario)`; guarda `eventos.recordatorio_enviado_at` y la ficha muestra "Enviado el …" o sugiere enviarlo el día anterior. Se puede volver a enviar (avisa que ya se envió).
- **Envío automático opcional (apagado):** existe el comando `php artisan recordatorios:enviar` (1 día antes a las 07:00, una vez por evento). Solo se programa si `RECORDATORIO_AUTOMATICO=true` en `.env` y el servidor ejecuta `php artisan schedule:run` cada minuto. Con eso activo aparece en el formulario la casilla por evento (`eventos.recordatorio_automatico`). Pruebas: `RecordatorioAutomaticoTest`.

### QR de asistencia y panel de control
- **QR del evento** (`RegistroQrController`, `eventos.token_registro`): botón "QR de asistencia" en la ficha → cartel para proyectar (pantalla completa), imprimir o descargar PNG. El asistente lo escanea y escribe su **correo o DPI**; se busca entre los contactos de la sede del evento y se marca presente (`asistencia_metodo = qr_evento`). Abierto desde 60 min antes del inicio hasta 30 min después del final (`Evento::REGISTRO_ANTES/DESPUES`). "Generar un QR nuevo" invalida el anterior. Página pública `/registro/{token}` (límite 20 por minuto).
- **QR personal** (`invitaciones.token`): va en el correo de invitación y en la página del invitado como "Su pase de entrada". Codifica `/escaneo/{token}`: el personal lo escanea con la cámara del celular con su sesión iniciada (permiso `eventos.asistencia`, respeta la sede) y la asistencia se marca (`qr_personal`). Fuera de horario solo informa.
- `Invitacion::registrarAsistencia()` centraliza el marcado (lista manual, QR del evento, QR personal). La lista de asistencia muestra el método.
- **Panel "Control del evento"** en la ficha (`Evento::control()`, vista `eventos/_control.blade.php`): invitaciones enviadas vs confirmadas vs no confirmadas vs asistencias vs inasistencias, y el cruce: confirmaron y asistieron / confirmaron y faltaron / no confirmaron pero asistieron / llegaron sin invitación, más el conteo por método de registro. Inasistencias = invitados por correo que no llegaron.
- El reporte por evento y su Excel incluyen las mismas columnas (enviadas, confirmadas, no confirmadas, dijeron que no, asistencias, inasistencias, %).
- Librería QR: chillerlan/php-qrcode (`App\Services\Qr`). Pruebas: `QrAsistenciaTest` (7).

### Tablero de inicio
- `DashboardController` + `resources/views/dashboard.blade.php`: filtros de período (30 días a 1 año, por defecto 6 meses) y sede.
- Indicadores: padres, catedráticos, eventos en los próximos 30 días, % de confirmación y % de asistencia (de eventos ya realizados).
- Gráfica de barras por mes (ApexCharts local en `public/assets/js/apexcharts.min.js`): invitaciones enviadas, confirmadas y asistencias. Paleta validada para daltonismo (skill dataviz), colores propios en modo claro y oscuro, leyenda y botón "Ver tabla".
- "Requiere atención": eventos de hoy (QR), próximos 7 días sin invitaciones, mañana sin recordatorio, realizados sin asistencia, contactos sin correo, sedes sin dirección, cada uno con su botón.
- Respuestas (confirmaron / dijeron que no / sin respuesta), asistencia por sede, próximos eventos con avance de confirmaciones y últimos eventos realizados.
- Pruebas: `TableroTest`.

## 4. Pendientes y decisiones abiertas

- [ ] **Direcciones reales de las 3 sedes** (hoy vacías; el sistema avisa en Sedes). Pedirlas al colegio.
- [ ] **Correo real** (decisión del usuario: por ahora en **modo de prueba**). `MAIL_MAILER=log` + `MAIL_LOG_CHANNEL=correos`: los correos se guardan en `storage/logs/correos.log`, no se envían. Cola `QUEUE_CONNECTION=sync`.
  Para activarlo: poner en `.env` los datos SMTP de la cuenta del colegio (Google Workspace / Microsoft 365) o de un servicio masivo (Brevo, Amazon SES, Mailgun); Gmail personal limita ~500/día.
  Con envíos grandes, cambiar a `QUEUE_CONNECTION=database` y dejar corriendo `php artisan queue:work`.
- [ ] Para que los enlaces del correo funcionen fuera de esta computadora, `APP_URL` debe ser la dirección pública del sistema (hoy `http://127.0.0.1:8001`).
- [ ] Dominio real del colegio para los correos (hoy `colegioesteca.edu.gt` es provisional).
- [x] Colores: paleta institucional azul marino + dorado (override de variables de Bootstrap
      en `public/assets/css/app.css`, sin tocar `style.bundle.css`).
- [ ] Publicar el sistema en un servidor (hosting con PHP 8.2 + MySQL) para que padres y catedráticos abran los enlaces desde su celular.
- [ ] Firmas de las constancias: hoy son líneas en blanco para firmar a mano; opcional agregar imagen de firma y sello.
- [ ] Ideas posibles a futuro: notificaciones por WhatsApp/SMS, portal para que el padre vea su historial.

## 5. Decisiones de diseño importantes

- Los botones del correo **solo abren** la página del invitado; la respuesta se guarda al pulsar (POST). Así los filtros antivirus de correo que abren enlaces no confirman por el invitado.
- Abrir la página marca `vista_at` ("vio la invitación"); no hay píxel de rastreo.
- En capacitaciones con cupo lleno ya no se aceptan confirmaciones nuevas (quien ya confirmó puede mantenerla).
- Quien se da de baja (`acepta_correos = false`) no recibe invitaciones, recordatorios ni avisos.
- Los destinatarios de un evento son siempre de la sede del evento, aunque el grupo sea de todas las sedes.

- La asistencia solo se marca desde el día del evento; antes se puede imprimir la lista para firmas.
- En las listas en memoria, "sin marcar" (null) y "ausente" (false) se distinguen con `whereStrict` (con `where` se confundían).

## 6. Convenciones del código

- Todo el texto de la interfaz en español; validaciones en español (`lang/es`).
- Rutas con segmento de tipo: `/contactos/{padres|catedraticos}`, `/eventos/{reuniones|capacitaciones}`.
- Confirmaciones de acciones delicadas: `<form data-confirmar="…">` (modal en `layouts/partials/confirmar.blade.php`).
- JS propio mínimo en `public/assets/js/app.js`; CSS propio en `public/assets/css/app.css`.
- Pruebas por fase en `tests/Feature/FaseNTest.php`.
- **Datos de prueba en la base real:** el usuario usa el sistema al mismo tiempo. Crear datos de demostración anotando sus IDs y borrarlos solo por esos IDs (nunca por fechas ni tablas completas).

## 7. Proyecto anterior (referencia)

En la misma carpeta raíz están `admin_crm_erp/` (Angular) y `api_crm_erp/` (Laravel 10), el CRM original que se adaptó a Guatemala.
Ya no se usan para este sistema; se conservan como respaldo.
