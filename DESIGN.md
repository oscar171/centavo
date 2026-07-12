# Centavo — Lineamientos de diseño (UI/UX)

> **Para el agente:** Sigue esta guía en TODA la UI. El objetivo es un look **limpio, profesional y confiable** tipo Mercury / Linear / Ramp / Copilot Money. Ante la duda, elige lo más sobrio. Activa los skills `tailwindcss-development` e `inertia-react-development` antes de maquetar.

---

## 0. Base que ya existe (no reinventar)
- **shadcn/ui** ya instalado en `resources/js/components/ui/` (Card, Button, Input, Select, Dialog, Badge, Skeleton, Sidebar, Sonner…). **Reúsalos siempre**; no crees componentes desde cero si ya existe el shadcn equivalente. Si falta `table`, agrégalo con la estructura estándar de shadcn.
- **Tailwind v4** con tokens semánticos en `resources/css/app.css` (`--background`, `--card`, `--primary`, `--muted-foreground`, `--border`, `--destructive`, `--chart-1..5`, sidebar…). **Usa las clases semánticas** (`bg-card`, `text-muted-foreground`, `border-border`) — **nunca** colores hardcodeados (`bg-[#fff]`, `text-gray-500`).
- **Dark mode** ya soportado (`.dark`). Todo lo que maquetes debe verse bien en claro y oscuro → por eso se usan tokens, no colores fijos.
- Tipografía: **Instrument Sans** (ya configurada). Iconos: **lucide-react**. Toasts: **sonner** (`<Toaster/>` ya montado).

---

## 1. Principios
1. **Claridad sobre decoración.** Es una app financiera: el dato manda. Mucho espacio en blanco, poca ornamentación.
2. **Consistencia.** Mismos espaciados, radios y jerarquías en todas las pantallas. Un solo patrón de página.
3. **Confianza.** Alineación perfecta de números, estados explícitos, nada "a medias". La app debe sentirse precisa.
4. **Neutro + un acento.** Base gris/negro/blanco (ya la tienes). El color se usa con intención (positivo/negativo/alerta), no para decorar.

---

## 2. Color

### Base
Mantén la paleta neutra actual. `--primary` (casi negro) es para acciones principales y texto fuerte. Jerarquía de texto:
- Título/valor importante → `text-foreground`
- Texto secundario/labels → `text-muted-foreground`
- Bordes/divisores → `border-border`
- Superficies → `bg-background` (página) y `bg-card` (tarjetas)

### Acento de marca "Centavo" (agregar)
Introduce **un** token de marca verde discreto (dinero/positivo). En `resources/css/app.css`, dentro de `:root` y `.dark`, agrega:
```css
:root {
  --brand: oklch(0.60 0.13 160);          /* verde sobrio */
  --brand-foreground: oklch(0.985 0 0);
}
.dark {
  --brand: oklch(0.68 0.14 160);
  --brand-foreground: oklch(0.205 0 0);
}
```
Y en el bloque `@theme`: `--color-brand: var(--brand); --color-brand-foreground: var(--brand-foreground);`
Úsalo **con moderación**: logo, un CTA destacado, el estado "conciliado", KPIs positivos. **No** lo conviertas en `--primary` ni lo pongas en todos los botones.

### Semántica de montos y estados (regla fija)
| Concepto | Estilo |
|---|---|
| Ingreso / crédito / devolución | `text-brand` (verde), signo `+` |
| Gasto / débito | `text-foreground` (neutro) — **no** todo rojo; rojo satura una lista de gastos |
| Alerta severidad **alta** / fraude | `text-destructive` + `Badge variant="destructive"` |
| Estado "conciliado / OK" | `Badge` con `bg-brand/10 text-brand` |
| Estado "requiere revisión" | `Badge` ámbar suave (`bg-amber-500/10 text-amber-600 dark:text-amber-400`) |
| Estado "procesando / pendiente" | `Badge variant="secondary"` + spinner |

---

## 3. Tipografía y números
- Escala: página `text-2xl font-semibold` (título), sección `text-lg font-medium`, cuerpo `text-sm`, labels/meta `text-xs text-muted-foreground`.
- **Números financieros SIEMPRE con cifras tabulares** para que aligneen en columnas: `className="tabular-nums"` (o `font-variant-numeric: tabular-nums`). Montos **alineados a la derecha** en tablas.
- Formatea moneda con `Intl.NumberFormat` (`{ style:'currency', currency: account.currency }`), no concatenando `$`.
- Fechas: formato corto y consistente (`Intl.DateTimeFormat`), alineadas a la izquierda.

---

## 4. Layout y espaciado
- Todas las páginas de la app van dentro del `AppLayout` (sidebar) por convención — no lo envuelvas a mano.
- Contenedor de contenido: `p-4 md:p-6`, ancho cómodo (`max-w-6xl` para dashboards, full para tablas anchas).
- Espaciado vertical entre secciones: `space-y-6`. Dentro de una tarjeta: `space-y-4`.
- Radios: usa los tokens (`rounded-xl` para tarjetas grandes, `rounded-lg`/`rounded-md` para inputs/badges).
- Grid de KPIs: `grid gap-4 md:grid-cols-3`. Tablas anchas: envolver en `overflow-x-auto` para no romper el layout en móvil.
- **Mobile-first**: verifica que el sidebar colapsa y las tablas hacen scroll horizontal, no desbordan la página.

---

## 5. Componentes por pantalla

### Encabezado de página
Título (`text-2xl font-semibold`) + subtítulo `text-sm text-muted-foreground` + acción principal a la derecha (`Button`). Breadcrumbs vía `Pagina.layout = { breadcrumbs: [...] }`.

### Tarjetas KPI (dashboard)
`Card` con: label `text-xs uppercase tracking-wide text-muted-foreground`, valor `text-2xl font-semibold tabular-nums`, y una micro-tendencia opcional. Nada de sombras pesadas — `border` + `bg-card` basta.

### Tabla de transacciones
- `Table` de shadcn. Columnas: Fecha · Descripción/Comercio · (Categoría) · **Monto (derecha, tabular-nums)** · Saldo.
- Filas con `hover:bg-muted/50`. Divisores con `border-border`. Densidad media (`py-2.5`).
- Comercio en `font-medium`, descripción cruda en `text-xs text-muted-foreground` debajo.
- Monto: verde `+` para créditos, neutro para débitos.

### Anomalías
Lista de `Card`/filas con icono lucide según tipo (`AlertTriangle`, `Copy`, `RefreshCcw`, `Repeat`), `Badge` de severidad, título en `font-medium`, descripción en `text-sm text-muted-foreground`, y monto implicado. Acciones "Descartar/Resolver" discretas (`Button variant="ghost" size="sm"`).

### Subida de PDF
Zona drag-&-drop: `border-2 border-dashed border-border rounded-xl p-8 text-center`, icono `Upload`, texto guía, hover `border-brand/50`. Al subir → toast `sonner` "Procesando…" + estado en vivo.

---

## 6. Estados (no olvidar ninguno)
- **Loading**: usa `ui/skeleton.tsx` con la **forma** del contenido real (filas de tabla, tarjetas), no un spinner genérico. Para statements en `processing`, skeleton de la tabla + badge "Procesando".
- **Empty states**: cuando no hay cuentas/statements/transacciones → tarjeta centrada con icono lucide tenue, título breve, 1 línea de ayuda y el CTA primario. Nunca una pantalla vacía.
- **Error / failed**: banner `bg-destructive/10 text-destructive` con motivo y acción de reintento.
- **needs_review**: banner ámbar explicando "Los saldos no cuadraron por $X, revisa los movimientos", sin bloquear la vista.

---

## 7. Interacción y microdetalle
- Transiciones sutiles (`transition-colors`, `tw-animate-css` ya disponible). Nada exagerado.
- Foco visible siempre (usa el `--ring` de los componentes; no quites outlines).
- Botones: 1 primario por vista; el resto `variant="outline"` o `"ghost"`. Acciones destructivas con confirmación (`Dialog`/`AlertDialog`).
- Feedback inmediato: toda mutación (crear cuenta, subir, descartar anomalía) confirma con `sonner`.
- Accesibilidad: labels en inputs, `aria-*` donde aplique, contraste suficiente (los tokens ya lo cuidan), targets táctiles ≥ 40px.

---

## 8. Do / Don't
**Do**
- Usar tokens semánticos y componentes shadcn.
- `tabular-nums` + alineación derecha en todo monto.
- Mucho espacio en blanco, jerarquía tipográfica clara.
- Probar cada pantalla en claro **y** oscuro, y en móvil.

**Don't**
- Colores hardcodeados o paletas nuevas por pantalla.
- Rojo para todos los gastos (solo para alertas reales).
- Sombras pesadas, gradientes llamativos, emojis como iconos (usa lucide).
- Tablas que desbordan el ancho de la página.
- Inventar componentes cuando shadcn ya lo cubre.

---

## 9. Norte visual
Referencia mental: **Mercury, Linear, Ramp, Copilot Money** — mucho blanco/neutro, tipografía nítida, datos perfectamente alineados, color solo con intención. Si una pantalla se ve "cargada", quita, no agregues.
