# Zonas y Partidos de Fútbol — Instructivo de uso

Plugin de WordPress para gestionar un torneo de fútbol con **fase de zonas** (fixture, resultados y tablas de posiciones) y **fase eliminatoria / playoffs** (llaves con avance automático), mostrando todo en el sitio mediante shortcodes y un hub navegable.

---

## 1. Requisitos

- WordPress 5.8+ con PHP 7.4+.
- Plugin **Inscripciones Fútbol** activo: de ahí salen los equipos (`if_equipo`) y sus escudos. Sin él no vas a poder elegir equipos ni ver la asignación por zonas.
- Los menús del plugin viven bajo el menú **Partidos** del admin (tipo de contenido *Partidos*, submenús *Posiciones*, *Equipos por zona* y *Ajustes*; las *Llaves* tienen su propio ítem).

---

## 2. Conceptos básicos

| Concepto | Qué es |
|---|---|
| **Zona** | Agrupación de equipos (Zona A, Zona B…). Es una taxonomía llamada *Zonas*. |
| **Partido** | Cruce entre dos equipos con zona, fecha/hora, lugar, jornada y resultado. |
| **Tabla de posiciones** | Se calcula sola a partir de los resultados cargados de cada zona. |
| **Llave (playoffs)** | Cuadro de eliminación directa generado desde los primeros puestos de las zonas. |
| **Hub** | Interfaz completa para el visitante con pestañas: posiciones, equipos, próximos partidos, resultados y playoffs. |

---

## 3. Puesta en marcha paso a paso

### Paso 1 — Crear las zonas

1. Ir a **Partidos → Zonas**.
2. Crear cada zona con su nombre (ej.: *Zona A*, *Zona B*).

> No hace falta configurar nada más: la cantidad de clasificados se define después en la llave.

### Paso 2 — Asignar los equipos a cada zona

1. Ir a **Partidos → Equipos por zona**.
2. Para cada equipo elegir su zona en la lista desplegable.
3. Presionar **Guardar zonas** (el botón queda fijo abajo mientras hacés scroll).

Los equipos que queden en *— Sin zona —* no aparecen en ninguna tabla ni en los listados por zona.

### Paso 3 — Crear el fixture (partidos)

1. **Partidos → Agregar nuevo**.
2. Completar el metabox **Datos del partido**, organizado en tres secciones:
   - **Cruce**: zona y equipos local / visitante (obligatorios, no pueden repetirse).
   - **Agenda**: fecha y hora, lugar/cancha y jornada (número o nombre). La fecha es la que ordena las listas del sitio; si se deja vacía el partido muestra *"A confirmar"*.
   - **Resultado**: estado (*Programado*, *Finalizado*, *Suspendido*) y, si finalizó, los goles.
3. El título del partido se genera solo: *Local vs Visitante*.

> **Consejo:** podés crear todos los partidos del torneo como *Programado* de una vez; cuando terminen se cambia el estado y se cargan los goles.

### Paso 4 — Cargar resultados

Abrir el partido, marcar **Finalizado** y cargar los goles. En cuanto guardás:

- Las **tablas de posiciones** se recalculan (puntos, PJ, G/E/P, GF, GC, DIF y la racha de los últimos 5).
- Si el partido pertenece a una llave, el **ganador avanza automáticamente** al cruce siguiente.

#### Definición por penales

Si el partido terminó empatado y se definió desde los doce pasos:

1. Marcá el estado **Finalizado** y cargá los mismos goles para ambos (empate).
2. En el bloque dorado **Definición por penales** cargá la serie de cada equipo (deben ser distintas).
3. Guardá: el ganador por penales queda marcado, se muestra el chip *Penales 4–3* y **avanza de ronda en los playoffs**.

> ⚠️ Validaciones: los penales solo aplican si hay empate y el partido está finalizado; no pueden quedar iguales (el sistema lo rechaza con un aviso).
>
> ℹ️ En las **tablas de zonas** un partido definido por penales sigue contando como **empate** (regla estándar de fase de liga). Los penales solo definen ganador en la fase eliminatoria.

### Paso 5 — Armar los playoffs

Cuando termine la fase de zonas:

1. Ir a **Partidos → Llaves → Agregar nueva** y ponerle nombre (ej.: *Playoff del Torneo Apertura*).
2. En el metabox:
   - **Zonas que clasifican**: tildar con chips todas las zonas que aportan equipos.
   - **Clasificados por zona**: cuántos mejores de cada zona entran (ej.: 2 → A1, B1, A2, B2).
   - Tildar **Regenerar fixture** y guardar.
3. El sistema arma el cuadro completo:
   - Intercala las posiciones entre zonas (A1 vs B2, B1 vs A2…) para evitar cruces intra-zona.
   - Ajusta el tamaño a potencia de 2 (con byes si sobran lugares).
   - Crea **todas las rondas desde el inicio**: Octavos/Cuartos → Semifinales → Final.
   - Muestra el panel **Fixture generado** con los partidos por ronda (enlaces directos para editarlos) y el lugar del campeón.

Durante el torneo:

- Cargá resultados normalmente: cada ganador **se ubica solo** en el cruce siguiente hasta definir al **campeón** (destacado en dorado en el front y en el admin).
- Si empatan y hay penales cargados, el ganador por penales avanza. Si la final termina en empate sin penales, el campeón queda vacante hasta desempatar.

> **Regenerar fixture** borra TODOS los partidos actuales de esa llave y los vuelve a crear según las posiciones del momento (incluye el cambio de "clasificados por zona"). Usalo con cuidado una vez empezado.

---

## 4. Mostrar todo en el sitio (shortcodes)

### `[zf_hub]` — el hub completo (recomendado)

Interfaz única con navegación por pestañas: **Posiciones · Equipos · Próximos · Resultados · Playoffs**, más buscador de equipos y perfiles de equipo con su posición, racha, últimos resultados y próximos partidos.

```
[zf_hub]
[zf_hub vista="resultados" titulo="Torneo Clausura"]
```

| Atributo | Valores | Descripción |
|---|---|---|
| `vista` | `posiciones` (default), `equipos`, `proximos`, `resultados`, `playoffs` | Pestaña inicial |
| `titulo` | texto | Encabezado del hub |

La navegación entre pestañas usa parámetros de URL (`?zf_vista=...`), así que cada vista tiene su propio enlace compartible. Los perfiles de equipo se acceden con `?zf_equipo=ID`.

### `[zf_zonas]`

Tarjetas con todas las zonas y sus equipos. Cada equipo enlaza a su perfil del hub.

### `[zf_tabla_posiciones]`

Tabla(s) de posiciones con resaltado verde para los puestos de clasificación y leyenda al pie.

```
[zf_tabla_posiciones]
[zf_tabla_posiciones zona="zona-a"]
[zf_tabla_posiciones zona="zona-a" clasificados="1"]
[zf_tabla_posiciones forma="0"]
```

| Atributo | Descripción |
|---|---|
| `zona` | Slug o ID para mostrar una sola zona. Sin él, muestra todas. |
| `clasificados` | Cuántos puestos resaltar. **Si no se indica, toma el valor configurado en la llave** (si cambiás "clasificados por zona", las tablas se actualizan solas). Con `0` se desactiva el resaltado. |
| `forma` | Con `forma="0"` oculta la columna Racha. |

### `[zf_proximos_partidos]`

```
[zf_proximos_partidos limite="8"]
[zf_proximos_partidos zona="zona-a" limite="5" futuros="1"]
```

`futuros="1"` oculta los que ya pasaron de fecha. Ordenados por fecha ascendente.

### `[zf_resultados]`

Últimos resultados cargados, ordenados por fecha descendente. Acepta `limite` y `zona`.

### `[zf_playoffs]`

Cuadro de eliminatorias con layout espejado: rondas previas a los costados y **final + campeón en el centro**.

```
[zf_playoffs]
[zf_playoffs llave="playoff-apertura"]
```

Sin `llave` muestra la primera publicada. Las etiquetas de ronda se ajustan solas al tamaño del cuadro (Cuartos de final, Semifinales, Final).

---

## 5. Referencia rápida del admin

| Pantalla | Para qué sirve |
|---|---|
| **Partidos → Todos los partidos** | Lista con columnas Zona, Fecha y hora y Resultado/Estado (incluye chip *Pen.* y etiqueta de ronda en los de playoffs; badge *Playoffs* si no tiene zona). |
| **Partidos → Llaves** | Lista de cuadros con columna *Fixture*: tamaño, progreso "X / Y jugados" o campeón definido. |
| **Editar llave** | Chips de zonas, clasificados, regenerar y panel *Fixture generado* con accesos a cada partido. |
| **Partidos → Posiciones** | Vista previa exacta de lo que ven tus visitantes. |
| **Partidos → Equipos por zona** | Asignación masiva de equipos. |
| **Partidos → Ajustes** | Puntos por victoria / empate / derrota (por defecto 3–1–0) y referencia de shortcodes. |

Estados de partido y cómo se ven:

- **Programado**: aparece en *Próximos* con cartel VS.
- **Finalizado**: aparece en *Resultados* con marcador; ganador resaltado en verde.
- **Suspendido**: badge rojo visible en todos los listados.

---

## 6. Preguntas frecuentes

**¿Puedo cambiar los puntos de la victoria?**
Sí, en *Ajustes del campeonato*. Las tablas se recalculan al instante.

**Cambié "clasificados por zona" pero las tablas seguían mostrando el valor viejo.**
Eso ya está corregido: las tablas leen el valor de la llave apenas lo guardás. El cuadro de playoffs en sí solo se rearma si tildás *Regenerar fixture* (para no borrar partidos jugados).

**¿Qué pasa si un partido de llave queda empatado sin penales?**
No avanza nadie: el cruce siguiente queda pendiente hasta que cargues penales o modifiques el resultado.

**¿Un equipo puede estar en dos zonas?**
El sistema toma una zona por equipo; la última asignación guardada es la válida.

**¿Cómo pruebo los playoffs sin esperar al fin de la liga?**
Creá la llave, regenerá el fixture y cargá resultados de prueba en los cruces de la primera ronda: vas a ver los avances, la final poblada y el campeón en cadena.

---

## 7. Changelog resumido

- **1.6.2** Corrige el guardado de penales (antes se borraban si el estado o el empate no coincidían al guardar).
- **1.6.1** Tokens oscuros autocontenidos (corrige textos blancos sobre blanco en el admin).
- **1.6.0** Etiquetas de ronda corregidas (Cuartos/Semifinales/Final) y cuadro espejado con final central.
- **1.5.0** Definición por penales: carga, validaciones, avance en llaves y visualización en front/admin.
- **1.4.0** Rediseño completo del panel de administración.
- **1.3.0** Módulo de playoffs (llaves) con generación y avance automático.
- **1.2.0** Hub con pestañas, buscador y perfiles de equipo.
- **1.1.0** Nuevo diseño del frontend (tarjetas, badges, racha).
