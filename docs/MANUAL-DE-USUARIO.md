# Manual de usuario — FleetFuel Portal

Sistema web para gestionar una distribuidora de combustible **ficticia**: flota (vehículos, conductores, tarjetas, flotas), ventas en estación (POS), entregas de diésel y reportes. Proyecto de portafolio con datos ficticios, sin afiliación con ningún distribuidor real.

> Versión: fase 3 (mapa, favoritos y preferencias incluidas).

---

## 1. Qué es el portal

Cubre cuatro áreas:

- **Flota:** vehículos, conductores, tarjetas de combustible, flotas y sus reglas y cuotas.
- **Ventas en estación:** registro de compras desde el POS, con control de cuota e idempotencia.
- **Entregas de diésel:** solicitud, programación, despacho y entrega, con historial y SLA.
- **Reportes y control:** consumo, excepciones de cuota, anomalías, eficiencia, exportación CSV y auditoría.

El dinero se maneja en USD y LBP y las cantidades/montos se guardan como decimales exactos (nunca con coma flotante).

## 2. Cómo entrar

Pantalla de **Iniciar sesión** (no hay registro público; las cuentas las crea el administrador o vienen con los datos demo).

- Se introduce **correo** y **contraseña**.
- Cada rol llega a su página inicial: *Panel* (administrador y gerente) o *Estación* (operador).
- Para salir: **Cerrar sesión** al final del menú lateral.

**Cuentas demo** (la contraseña es la variable `DEMO_PASSWORD` del `.env`):

- `admin@fleetfuel.test`
- `manager.atlas@fleetfuel.test`, `manager.cedar@fleetfuel.test`
- `operator.beirut@fleetfuel.test`, `operator.tripoli@fleetfuel.test`

## 3. Roles y permisos

| Función | Administrador | Gerente de empresa | Operador de estación |
|---|---|---|---|
| Panel con totales y alertas | Sí (todas las empresas) | Sí (su empresa) | No |
| Lista de transacciones | Todas | Su empresa | Su estación |
| Registrar compra (POS) | No | No | Sí |
| Reportes y CSV | Sí | Sí | No |
| Entregas | Todas | Su empresa | No |
| Vehículos, conductores, tarjetas, flotas, importación | Todas | Su empresa | No |
| Mapa de estaciones | Sí | Sí | No |
| Notificaciones | Sí | Sí | No |
| Favoritos y preferencias | Sí | Sí | Sí |
| Empresas, estaciones, productos, tasas, auditoría | Solo administrador | No | No |

Un registro de otra empresa responde “no encontrado”, incluso escribiendo la URL.

## 4. Navegación

El menú lateral agrupa las pantallas según el rol. En pantallas grandes puede **plegarse** con el botón `☰` de la barra superior (el estado se recuerda); en móvil se abre como panel deslizable con el botón **Menú**.

- **General:** Panel (o Estación), Transacciones, Reportes, Entregas, Notificaciones, Mapa.
- **Flota:** Empresas, Vehículos, Conductores, Tarjetas, Flotas, Importar.
- **Datos de referencia:** Estaciones, Productos y precios.
- **Administración:** Tasas de cambio, Reglas de notificación, Auditoría.
- **Cuenta** (abajo): nombre, rol, *Preferencias*, *Cerrar sesión*.

## 5. Panel (dashboard)

Página de inicio de administrador y gerente. Resume el mes en curso (calendario de Beirut) frente al mes anterior.

- **Totales del mes:** compras, litros, gasto en USD y LBP, con el mes anterior como referencia.
- **Alertas de cuota:** tarjetas bloqueadas o por encima de su límite este mes.
- **Entregas abiertas**, **datos de flota y tasa vigente**.
- **Consumo por flota:** litros del mes frente a la cuota (%) con estado.
- **Compras recientes** y acceso al **mapa**.
- **Favoritos:** accesos rápidos reordenables.

En *Preferencias → Dashboard* eliges qué widgets ves y en qué orden.

## 6. Transacciones

Lista de compras. Alcance por rol: administrador todas, gerente su empresa, operador su estación.

- **Filtros:** fechas (por defecto el mes de Beirut actual), tarjeta, estación, producto y empresa (solo admin).
- **Totales** del filtro completo (no solo de la página) y **exportación CSV**.
- **Detalle** inmutable: precio, tasa, litros, vehículo, conductor y capacidad de tanque del momento.

Las compras aceptadas no se editan ni se recotizan.

## 7. Estación (POS)

Pantalla del **operador de estación** para registrar una venta:

1. Identifica tarjeta, producto y litros, hora del evento y (si aplica) odómetro.
2. El sistema valida tarjeta, empresa, asignaciones, producto, reglas de flota, precio y tasa.
3. Comprueba la cuota mensual y registra la compra, actualizando el contador del mes.

Reglas del POS:

- Cada envío lleva una **referencia externa** única: reintentos idénticos devuelven la misma compra; con datos distintos se rechaza.
- Solo eventos de las **últimas 72 horas**, nunca del futuro.
- La estación se toma de la sesión del operador, nunca del formulario.

## 8. Entregas de diésel

El gerente solicita y puede cancelar sus pedidos pendientes; el administrador programa, despacha y entrega.

| Estado | Quién | Qué ocurre |
|---|---|---|
| Pendiente | Gerente / admin | Se crea con dirección, gobernación, litros y ventana preferida; primer historial. |
| Programada | Administrador | Se fija ventana y camión. |
| En camino | Administrador | Sale a reparto. |
| Entregada | Administrador | Estado final; se guarda la hora. |
| Cancelada | Gerente (su pendiente) / admin | Requiere motivo. |

Una entrega **no toca tarjetas, cuotas ni el registro de compras**. Si dos usuarios cambian el estado a la vez, el segundo recibe aviso de que el pedido ya cambió.

## 9. Flotas

Una **flota** agrupa vehículos, conductores y tarjetas de una empresa y les asigna una cuota mensual agregada y reglas.

- **Crear/editar** con nombre, descripción y cuota (litros y/o USD); nombre único dentro de la empresa.
- **Asignar/desasignar** miembros de la empresa.
- **Reglas:** productos permitidos, estaciones permitidas, máximo de litros por transacción y máximo de transacciones por día.
- **Activar/desactivar** y **eliminar** (los miembros quedan “sin flota” y siguen funcionando).

Las reglas se comprueban al aceptar una compra: si se incumplen, se rechaza. La cuota agregada es solo una señal (nunca bloquea por sí sola).

## 10. Vehículos, conductores y tarjetas

- **Vehículos y conductores:** empresa asignada por el servidor (el gerente no la elige); placa y licencia únicas y normalizadas a mayúsculas; pueden asignarse a una flota.
- **Tarjetas:** límite mensual de litros/USD (vacío = ilimitado) y estado *activa/bloqueada/archivada*; se les asigna vehículo, conductor, restricción de producto y flota. La **asignación se bloquea tras la primera compra**; los límites y el estado sí cambian. Archivar es definitivo.

En el detalle de una flota y en la edición de un vehículo hay una **estrella** para añadirlo a favoritos.

## 11. Importación masiva (CSV)

Para administrador y gerente (el admin elige empresa; el gerente usa la suya):

1. Descarga la **plantilla** del tipo (vehículos, conductores o tarjetas).
2. Rellena el archivo respetando el encabezado exacto.
3. Marca **“Simular (no guardar)”** si quieres validar sin escribir.
4. Sube el archivo y revisa la **tabla de resultados** (fila, campo, mensaje).

Máximo **5.000 filas / 5 MB**. Las filas válidas se guardan aunque otras fallen.

## 12. Notificaciones

**Campana** en la cabecera con el número de no leídas (se actualiza cada minuto) y las últimas cinco.

- **Bandeja:** filtros por leídas/no leídas y por tipo; marcar una o todas como leídas.
- **Reglas:** para un evento, en una compañía (y opcionalmente una flota), envía por un canal: *en la aplicación*, *correo* o *registro* (auditoría + log).

| Evento | Cuándo se dispara |
|---|---|
| Cuota excedida | Se rechaza una compra por superar el límite de la tarjeta. |
| Aviso de cuota | Una compra deja la tarjeta en cierto % de su cuota (usa el umbral de la regla). |
| Tarjeta bloqueada | Se bloquea una tarjeta. |
| Anomalía detectada | Dos cargas del mismo vehículo con menos de 30 minutos. |
| Entrega completada / cancelada | Un pedido llega a entregado o se cancela. |
| Regla de flota incumplida | Una compra es rechazada por una regla de flota. |

El umbral solo aplica al *aviso de cuota*. Un mismo evento sobre la misma entidad no se repite más de **una vez por hora**.

## 13. Mapa de estaciones

Vista geográfica (administrador y gerente). Cada estación con coordenadas es un pin:

- **Verde:** activa y con compras hoy.
- **Azul:** activa sin compras hoy.
- **Gris:** inactiva.

Al alejar, los pines se agrupan con el número de estaciones. El popup muestra nombre, dirección, estado, productos, compras y litros del día y el último tanqueo. Filtros por estado, producto y nombre; botón “Centrar en mi ubicación”. Las estaciones sin coordenadas se listan debajo con enlace para editarlas (admin).

## 14. Reportes y exportación CSV

Seis reportes con filtros de fecha (mes de Beirut por defecto), empresa (admin) y flota:

| Reporte | Qué responde |
|---|---|
| Consumo | Litros y montos por empresa, vehículo o producto. |
| Top estaciones | Las estaciones con más litros (máx. 10). |
| Excepciones de cuota | Tarjetas bloqueadas o en/por encima de su límite este mes. |
| Anomalías | Sobre-llenados de tanque y cargas rápidas (< 30 min). |
| Eficiencia | Estimación km/L full-to-full con lecturas de odómetro. |
| SLA de entregas | Horas desde la solicitud hasta la entrega, por gobernación. |

El **CSV de transacciones** usa los mismos filtros y alcance que la lista, con encabezado, escape correcto y neutralización de fórmulas.

## 15. Favoritos

- **Estrella** en el detalle de una flota o edición de un vehículo para añadir/quitar.
- **Panel “Favoritos”** en el dashboard, reordenable arrastrando.
- Solo entidades que puedes ver; un duplicado se rechaza.

## 16. Preferencias

Desde el menú de la cuenta; se aplican en la siguiente navegación sin cerrar sesión.

- **General:** idioma, formato de fecha y filas por página.
- **Dashboard:** flota por defecto (preseleccionada en reportes) y widgets visibles/orden.
- **Mapa:** zoom inicial y centro (latitud/longitud), con “Usar mi ubicación”.

## 17. Administración (solo administrador)

- **Empresas:** crear y activar/desactivar; una empresa inactiva deja su flota en solo lectura.
- **Estaciones:** crear, editar y activar/desactivar; las coordenadas alimentan el mapa.
- **Productos y precios:** publicar precios por producto con vigencia.
- **Tasas de cambio USD/LBP:** ver la vigente y su origen; ajustes manuales temporales. La sincronización automática corre con el comando programado `rates:sync`.
- **Auditoría:** registro inmutable de cambios sensibles, con filtros por actor, acción, tipo y empresa.

## 18. Reglas de negocio que conviene conocer

- **Mes de cuota:** calendario de Beirut; horas guardadas en UTC y mostradas en hora de Beirut.
- **Cuotas:** la de una tarjeta **bloquea** al superarse; la agregada de una flota es solo **señal**.
- **Idempotencia del POS:** referencia externa única; mismo contenido devuelve la compra original, contenido distinto se rechaza.
- **Instantáneas:** cada compra guarda precio, tasa, vehículo, conductor y capacidad de tanque del momento; nunca se recotiza.
- **Tasa de cambio:** sin tasa válida no se valora una compra; nunca se adivina ni se usa 1:1.
- **Entregas:** máquina de estados; no afectan tarjetas ni el registro de compras.

## 19. Tareas frecuentes

**Registrar una compra (operador):** *Estación* → tarjeta, producto y litros (hora si aplica) → enviar → revisar confirmación y saldo.

**Solicitar y gestionar una entrega:** gerente *Entregas → Solicitar* (dirección, gobernación, litros, ventana); admin programa, despacha y entrega; el gerente puede cancelar si sigue pendiente.

**Crear una flota con reglas:** *Flotas → Agregar* (nombre y cuota) → añadir reglas → asignar miembros.

**Importar vehículos desde CSV:** *Importar → Vehículos* → descargar plantilla → subir (o simular) → corregir errores → reimportar.

**Configurar aviso de cuota al 80 %:** *Reglas de notificación → Agregar* → evento “Aviso de cuota”, canal “En la aplicación”, umbral 80 “Al menos” (opcional: limitar a una flota).

**Exportar el detalle contable:** *Transacciones* → fijar fechas y filtros → exportar CSV.

## 20. Mensajes de error comunes

| Mensaje | Significado | Qué hacer |
|---|---|---|
| Cuota excedida | La compra supera el límite mensual (litros o USD). | Revisa límites/consumo; ajusta o usa otra tarjeta. |
| Tarjeta bloqueada | Tarjeta bloqueada o archivada. | Desbloquéala (si aplica) desde Tarjetas. |
| Empresa inactiva | La empresa de la tarjeta está desactivada. | Reactívala (administrador). |
| Sin precio / sin tasa | No hay precio o tasa válida a esa hora. | Publica precio/tasa y reintenta. |
| El pedido cambió | Otra persona modificó la entrega antes. | Recarga y revisa el estado. |
| Referencia repetida | Referencia externa reutilizada con datos distintos. | Usa una referencia nueva. |
| Demasiadas solicitudes | Límite de peticiones alcanzado. | Espera unos segundos. |

## 21. Preguntas frecuentes

- **¿Puedo editar o borrar una compra ya registrada?** No; el registro es inmutable. Se corrige con un nuevo movimiento.
- **¿Por qué no veo registros de otra empresa?** El sistema aísla por empresa; responden “no encontrado”.
- **¿Cómo cambio la contraseña?** El proyecto demo no incluye autogestión; las claves las administra quien instala el sistema.
- **¿Litros y montos usan decimales exactos?** Sí; en la API viajan como cadenas.
- **¿Funciona en el móvil?** Sí; el menú es deslizable y las tablas anchas se desplazan en su contenedor.

## 22. Cómo funciona por dentro (breve)

- **Stack:** Laravel 13 (PHP 8.3), MySQL 8.4, Blade + Bootstrap 5 + jQuery, Vite, Sanctum (API) y Fortify (login).
- **Multi-tenant por empresa:** listados y búsquedas filtrados por el alcance del usuario.
- **Lógica en servicios:** ingesta POS, cuotas, entregas, flotas, importación y notificaciones viven en servicios compartidos por web y API.
- **API REST** en `/api/v1` con tokens por rol y contrato OpenAPI (`docs/api/openapi.json`).
- **Calidad:** pruebas sobre MySQL, Larastan y Pint.

Ejecutar en local (resumen):

```bash
composer install
php artisan key:generate      # solo la primera vez
php artisan migrate
php artisan demo:seed         # datos y cuentas demo
npm install && npm run build
php artisan serve             # http://127.0.0.1:8000
```

Con Docker: `make setup` y `make up` (ver README y documentación del repositorio).
