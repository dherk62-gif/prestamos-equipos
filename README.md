# Sistema web de gestión de préstamos de equipos y recursos

Proyecto final integrador · Programación V · Universidad Autónoma de Centro América
**Entregable 2: avance del código**

Aplicación web cliente-servidor para registrar equipos, prestarlos, recibir devoluciones y controlar multas por retraso en una institución educativa.

**Repositorio:** https://github.com/dherk62-gif/prestamos-equipos
**Integrantes:** Dylan Cordero Hernandez · Daniel Castillo Valerio

## Estado de este avance (Entregable 2)

Versión funcional parcial del sistema. **Incluida en el repositorio:** inicio de sesión con roles, panel principal, CRUD de usuarios, categorías y equipos, base de datos con su diagrama entidad-relación y esta documentación.

**Se incorpora en el siguiente avance:** los módulos de préstamos, multas y reportes. Mientras no estén en el repositorio, los botones del panel y los enlaces del menú que apuntan a esos módulos no tienen destino.

## Tecnologías

| Capa | Herramientas |
|---|---|
| Front End | HTML5, CSS3, JavaScript y Bootstrap 5.3 |
| Back End | PHP 8 (PDO, sesiones, `password_hash`) |
| Base de datos | MySQL / MariaDB, motor InnoDB |
| Servidor local | Apache mediante XAMPP |
| Control de versiones | Git y GitHub |

## Instalación en XAMPP

1. Encienda **Apache** y **MySQL** desde el panel de XAMPP.
2. Copie la carpeta del proyecto en `C:\xampp\htdocs\prestamos-equipos` (el nombre de la carpeta debe coincidir con `BASE_URL` en `config/app.php`).
3. Abra `http://localhost/phpmyadmin`, entre a la pestaña **Importar** y cargue `database/prestamos_equipos.sql`. El script crea la base `prestamos_equipos`, las tablas, las vistas y los datos de prueba.
4. Si su MySQL tiene contraseña para `root`, cámbiela en `config/database.php`.
5. Abra `http://localhost/prestamos-equipos/`.

### Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@prestamos.test | Admin123* |
| Encargado | encargado@prestamos.test | Encargado123* |
| Usuario | maria.jimenez@prestamos.test | Usuario123* |

Los datos de prueba incluyen un préstamo vencido, uno devuelto con retraso (multa pendiente de Sofía Mora) y equipos en todos los estados.

## Estructura del proyecto

```
prestamos-equipos/
├── index.php, login.php, logout.php, dashboard.php
├── config/
│   ├── app.php               Nombre, BASE_URL, multa por día, zona horaria
│   └── database.php          Conexión PDO a MySQL
├── includes/
│   ├── init.php              Arranque común (config, sesión, funciones)
│   ├── auth.php              Sesión y control de acceso por rol
│   ├── functions.php         Escape de HTML, CSRF, mensajes, formatos
│   ├── componentes.php       Piezas de interfaz reutilizables
│   └── header.php, footer.php   Menú lateral y cierre de página
├── modules/
│   ├── usuarios/             index · form (crear/editar) · eliminar
│   ├── categorias/           index · form (crear/editar) · eliminar
│   ├── equipos/              index · form (crear/editar) · eliminar
│   ├── prestamos/            index · nuevo · devolver        (próximo avance)
│   ├── multas/               index · pagar                   (próximo avance)
│   └── reportes/             index (vencidos, más prestados) (próximo avance)
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── database/prestamos_equipos.sql
└── docs/diagrama_er.png, diagrama_er.dot
```

Cada módulo sigue el mismo patrón: `index.php` lista y busca, `form.php` crea o edita (valida en el servidor y guarda con consultas preparadas) y `eliminar.php` borra por POST con token CSRF.

## Base de datos

Seis tablas relacionadas con llaves primarias y foráneas, más tres vistas de consulta.

![Diagrama entidad-relación](docs/diagrama_er.png)

```mermaid
erDiagram
    roles ||--o{ usuarios : tiene
    categorias ||--o{ equipos : clasifica
    usuarios ||--o{ prestamos : recibe
    usuarios ||--o{ prestamos : registra
    equipos ||--o{ prestamos : "se presta en"
    prestamos ||--o| multas : genera
    usuarios ||--o{ multas : debe
```

| Tabla | Para qué sirve | Llaves foráneas |
|---|---|---|
| `roles` | Administrador, encargado y usuario | — |
| `usuarios` | Cuentas de acceso y personas que reciben equipos | `id_rol` → roles |
| `categorias` | Agrupa los equipos | — |
| `equipos` | Inventario con estado: disponible, prestado, mantenimiento, baja | `id_categoria` → categorias |
| `prestamos` | Fecha de salida, fecha límite y fecha de devolución de cada préstamo | `id_usuario`, `id_equipo`, `id_encargado` |
| `multas` | Multa automática por retraso, una por préstamo | `id_prestamo`, `id_usuario` |

Vistas: `v_prestamos_detalle` (préstamos con usuario, equipo y estado real: en curso, vencido o devuelto), `v_prestamos_vencidos` y `v_equipos_mas_prestados`.

## Roles y permisos

La tabla incluye los permisos de los módulos que se incorporan en el próximo avance.

| Acción | Administrador | Encargado | Usuario |
|---|:-:|:-:|:-:|
| Iniciar y cerrar sesión | Sí | Sí | Sí |
| Ver equipos disponibles | Sí | Sí | Sí |
| Ver sus propios préstamos y multas | Sí | Sí | Sí |
| Registrar y editar equipos y categorías | Sí | Sí | No |
| Eliminar equipos | Sí | No | No |
| Registrar y editar usuarios | Sí | Solo usuarios comunes | No |
| Eliminar usuarios | Sí | No | No |
| Registrar préstamos y devoluciones | Sí | Sí | No |
| Marcar multas como pagadas | Sí | Sí | No |
| Ver todos los préstamos, multas y reportes | Sí | Sí | No |

## Reglas de negocio y dónde se aplican

Las reglas de préstamos y multas se activan cuando se incorporen esos módulos (próximo avance). Las de usuarios y equipos ya están en este repositorio.

| Regla | Dónde está |
|---|---|
| Un equipo prestado no puede prestarse de nuevo hasta que sea devuelto | `modules/prestamos/nuevo.php`: transacción con `SELECT … FOR UPDATE` sobre el equipo; la lista solo muestra equipos disponibles |
| Al prestar pasa a «prestado»; al devolver vuelve a «disponible» | `nuevo.php` y `devolver.php`, dentro de la misma transacción que el préstamo |
| Si la devolución supera la fecha límite se genera una multa automática | `devolver.php`: días de retraso × `MULTA_POR_DIA` (`config/app.php`) |
| Un usuario con multas pendientes no puede pedir préstamos | `nuevo.php`: validación en el servidor; además la lista deshabilita a esos usuarios |
| Solo el administrador elimina usuarios y equipos | `eliminar.php` de ambos módulos usa `require_rol('administrador')` |
| Cada préstamo guarda fecha de salida, límite y devolución | Tabla `prestamos` y restricciones `CHECK` sobre las fechas |

## Descripción de los módulos desarrollados

**Incluidos en este avance**

- **Inicio de sesión:** valida correo y contraseña con `password_verify`, regenera el id de sesión y bloquea usuarios inactivos.
- **Panel:** el personal ve equipos por estado, préstamos vencidos y en curso, y multas pendientes. El usuario común ve sus préstamos en curso y sus multas.
- **Usuarios:** registro, búsqueda por nombre, cédula o correo, filtro por rol, edición y eliminación. La cédula se valida con 9 dígitos sin guiones, el correo debe ser único y nadie puede quitarse su propio acceso de administración.
- **Categorías:** registro, búsqueda, edición y eliminación (se bloquea si tiene equipos).
- **Equipos:** registro, búsqueda por código, nombre, marca o modelo, filtros por categoría y estado, edición y eliminación. Un equipo prestado no permite cambiar su estado a mano.

**Por incorporar en el siguiente avance**

- **Préstamos:** registro con fecha límite, listado con pestañas, búsqueda y registro de devoluciones.
- **Multas:** listado de pendientes y pagadas, y pago de multas.
- **Reportes:** préstamos vencidos, equipos más prestados e historial por usuario.

## Funcionalidades terminadas y pendientes

**Terminadas e incluidas en este repositorio**

- Inicio y cierre de sesión con roles y permisos
- Panel principal
- CRUD completo de usuarios, categorías y equipos
- Búsquedas y filtros en los listados de usuarios y equipos
- Validaciones del lado del servidor, consultas preparadas y protección CSRF
- Base de datos con tablas, llaves foráneas, vistas y diagrama entidad-relación

**Pendientes de incorporar al repositorio (siguiente avance)**

- Módulo de préstamos con devoluciones
- Multas automáticas y pago de multas
- Reportes de vencidos, más prestados e historial por usuario

**Mejoras previstas para la entrega final**

- Cambiar la contraseña desde el perfil y recuperarla por correo
- Paginación de los listados
- Exportar reportes a PDF o Excel
- Avisos por correo cuando un préstamo está por vencer
- Pruebas finales de usabilidad y manual de usuario

## Flujo de trabajo con Git

Cada integrante configura su propio nombre y correo para que el historial muestre quién hizo cada aporte:

```bash
git config user.name  "Nombre Apellido"
git config user.email "correo@ejemplo.com"
```

Sugerencia: una rama por módulo (`feature/usuarios`, `feature/prestamos`…), commits pequeños con mensajes claros y *pull request* para unir a `main`.

## Evidencias de pantallas

Capturas de las pantallas incluidas en este avance (carpeta `docs/capturas/`).

| Pantalla | Captura |
|---|---|
| Inicio de sesión | ![Inicio de sesión](docs/capturas/01-login.png) |
| Panel principal (administrador) | ![Panel](docs/capturas/02-panel.png) |
| Categorías | ![Categorías](docs/capturas/03-categorias.png) |
| Equipos con filtros | ![Equipos](docs/capturas/04-equipos.png) |
| Listado de usuarios | ![Usuarios](docs/capturas/05-usuarios.png) |
| Formulario de usuario con validaciones | ![Formulario de usuario](docs/capturas/06-usuario-formulario.png) |
| Panel de un usuario común | ![Panel de usuario](docs/capturas/07-panel-usuario.png) |

## Aportes por integrante

Los aportes se pueden verificar en el historial de commits del repositorio (`git log` o la pestaña *Commits* en GitHub).

| Integrante | Aportes |
|---|---|
| Dylan Cordero Hernandez | Estructura base, conexión a MySQL, login y control de roles; módulo de usuarios con validaciones; script SQL, vistas y diagrama entidad-relación; panel principal y documentación |
| Daniel Castillo Valerio | Módulos de categorías y equipos con búsqueda y filtros |