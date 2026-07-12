# Centavo — Plan de implementación del MVP

> **Para el agente ejecutor:** Este documento es autocontenido. Impлеméntalo por fases, en orden. Cada fase tiene su *Definition of Done* y sus tests. No avances a la siguiente fase sin que los tests de la actual pasen. Responde en español (regla global del usuario).

---

## 0. Contexto y objetivo

**Centavo** es un SaaS donde el usuario sube el **PDF de su estado de cuenta bancario**, y con IA se extraen los movimientos, se **validan contra los saldos** (para que los números nunca estén mal), y se **detectan anomalías** (cargos duplicados, ráfagas de cargos sospechosos, suscripciones recurrentes, reversos/chargebacks).

El caso de uso que originó la idea: el usuario tuvo ~56 cargos de FanDuel ($5,500) y 2 de Mercari ($2,769.97) en un mes; la mayoría fueron devueltos y el banco emitió un crédito provisional. Centavo debe poder **reconstruir esa historia automáticamente** desde el PDF.

### Alcance del MVP (acordado con el usuario)
- **Cuentas**: el usuario crea "cuentas" (nombre + banco + últimos 4 dígitos). Los PDFs se suben **asociados a una cuenta**.
- **Subida + extracción con IA** (PDF nativo a Claude, sin parser por banco).
- **Validación determinística de saldos** (capa anti-error de la IA).
- **Detección de anomalías** (reglas deterministas + una pasada de IA que explica).
- **Dashboard** con totales, gasto por comercio y alertas de anomalías.

### Fuera de alcance (NO implementar en el MVP)
- Conexión directa al banco (Plaid/Belvo) — fase futura.
- Presupuestos/metas de ahorro.
- Multi-moneda avanzada (asumir 1 moneda por cuenta, default USD).
- Pagos/suscripciones del propio SaaS.

> Regla del usuario: **implementar solo lo pedido**, no agregar features "de prioridad alta" no solicitados.

---

## 1. Stack detectado (RESPETAR — no cambiar)

Este proyecto es el **Laravel React Starter Kit oficial**. Difiere de otros proyectos del usuario. Sigue estas convenciones:

| Área | Tecnología | Nota |
|---|---|---|
| Backend | Laravel **13** (PHP 8.3) | |
| Auth | **Fortify** (no Breeze) | ya configurado |
| Inertia | **v3** (`inertiajs/inertia-laravel ^3`, `@inertiajs/react ^3`) | |
| Rutas/acciones front | **Wayfinder** | genera helpers en `@/routes` y `@/actions/...` |
| Front | **React 19** + TypeScript | páginas en `resources/js/pages/` (**minúscula**) |
| CSS | **Tailwind v4** (`@tailwindcss/vite`) | no hay `tailwind.config.js` clásico |
| Componentes UI | **shadcn/ui ya instalado** en `resources/js/components/ui/` | reusar: `card`, `button`, `input`, `select`, `dialog`, `table`(crear si falta), `badge`, `sonner` (toasts) |
| Iconos | **lucide-react** | |
| Tests | **Pest v4** (no PHPUnit) | `php artisan make:test --pest` |
| IA | **`laravel/ai ^0.9`** ✅ ya instalado | |
| BD | **SQLite** por defecto (`database/database.sqlite`) | ok para MVP; usar tipos `decimal` |

### Patrones del starter kit (imitar exactamente)
- **Layouts por convención** (`resources/js/app.tsx`): las páginas bajo `settings/` usan `[AppLayout, SettingsLayout]`; el resto usa `AppLayout`. Para páginas nuevas dentro de la app autenticada, **no** envuelvas manualmente el layout — solo exporta el componente y, si quieres breadcrumbs, agrega:
  ```tsx
  MiPagina.layout = { breadcrumbs: [{ title: 'Cuentas', href: accounts.index() }] };
  ```
- **Rutas**: se referencian en React con Wayfinder, p. ej. `import { dashboard } from '@/routes'`. Al crear controllers, Wayfinder autogenera `@/actions/App/Http/Controllers/...`. Corre `php artisan wayfinder:generate` (o `npm run dev`, que lo regenera) tras crear/editar rutas.
- **Formularios**: usar el componente `<Form {...Controller.action.form()}>` de Inertia v3 con render-props `{({ processing, errors }) => ...}` (ver `resources/js/pages/settings/profile.tsx` como referencia).
- **Navegación**: agregar ítems en `resources/js/components/app-sidebar.tsx` (array `mainNavItems`).

### Skills a activar (obligatorio)
Antes de escribir código en cada dominio, activa el skill correspondiente:
- `ai-sdk-development` — para todo lo de `laravel/ai` (agentes, structured output, attachments, fakes).
- `inertia-react-development` — para páginas/forms/navegación React.
- `laravel-best-practices` — para controllers, models, jobs, policies, queries.
- `tailwindcss-development` — para el maquetado (Tailwind v4).

---

## 2. Decisiones tomadas

1. **Extracción**: PDF nativo → Claude vía `laravel/ai` con **structured output**. Sin regex/parser por banco.
2. **Modelo IA**: `claude-sonnet-4-6` para extracción y para anomalías (balance costo/precisión). Configurable; se puede subir a `claude-opus-4-8` si hiciera falta más precisión.
3. **Procesamiento en cola**: la extracción corre en un **Job** (`ProcessStatement`) para no bloquear la request de subida.
4. **La IA NO calcula, solo extrae.** Los totales/validación los hace **PHP** de forma determinista (reconciler). Si no cuadra → `needs_review`.
5. **Cuentas** como entidad raíz: `User → Account → Statement → Transaction`. Anomalías en tabla aparte.
6. **Tests sin gastar tokens**: usar `StatementExtractor::fake([...])` de `laravel/ai`.

---

## 3. Arquitectura y flujo

```
Usuario
  │  crea Cuenta (nombre, banco, últimos 4)
  ▼
[Cuentas] ──selecciona cuenta──► Sube PDF
  │
  ▼
StatementController@store
  - valida (pdf, mimetype, tamaño)
  - guarda archivo en disco privado
  - crea Statement (status = pending)
  - dispatch ProcessStatement (cola)
  ▼
Job ProcessStatement
  1. status = processing
  2. StatementExtractor (laravel/ai) → JSON estructurado  [IA]
  3. StatementReconciler → valida saldos                  [PHP determinista]
       - cuadra  → status = processed, guarda transacciones
       - no cuadra → status = needs_review (guarda igual + diff)
  4. AnomalyDetector → genera anomalías                   [reglas + IA]
  5. (opcional) borra el PDF si config lo indica
  ▼
[Statement show]  tabla de movimientos + resumen + estado + anomalías
[Dashboard]       totales, gasto por comercio, alertas
```

---

## 4. Modelo de datos (migraciones)

Crear con `php artisan make:model X -mf` (modelo + migración + factory). Todos los montos en **`decimal(15,2)`**, fechas en `date`.

### `accounts`
```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name');                 // "Cuenta principal"
$table->string('bank');                 // "Wells Fargo"
$table->string('account_type')->nullable(); // checking | savings | credit
$table->string('last_four', 4)->nullable();
$table->string('currency', 3)->default('USD');
$table->timestamps();
```

### `statements`
```php
$table->id();
$table->foreignId('account_id')->constrained()->cascadeOnDelete();
$table->date('period_start')->nullable();
$table->date('period_end')->nullable();
$table->decimal('beginning_balance', 15, 2)->nullable();
$table->decimal('ending_balance', 15, 2)->nullable();
$table->decimal('total_deposits', 15, 2)->nullable();
$table->decimal('total_withdrawals', 15, 2)->nullable();
$table->string('original_filename');
$table->string('file_path')->nullable();     // en disco privado; null si se borró
$table->string('status')->default('pending'); // pending|processing|processed|needs_review|failed
$table->boolean('is_reconciled')->default(false);
$table->decimal('reconciliation_diff', 15, 2)->nullable(); // 0.00 si cuadra
$table->text('failure_reason')->nullable();
$table->timestamp('processed_at')->nullable();
$table->timestamps();
```
> Usa un **Enum PHP** `StatementStatus` (string-backed) en lugar de strings sueltos, y castéalo en el modelo.

### `transactions`
```php
$table->id();
$table->foreignId('statement_id')->constrained()->cascadeOnDelete();
$table->foreignId('account_id')->constrained()->cascadeOnDelete(); // denormalizado para queries
$table->date('date');
$table->text('description');
$table->decimal('amount', 15, 2);              // siempre POSITIVO
$table->string('direction');                   // credit | debit  (Enum TransactionDirection)
$table->decimal('running_balance', 15, 2)->nullable(); // "ending daily balance" si viene
$table->string('reference')->nullable();       // Ref # del banco (para dedupe)
$table->string('merchant')->nullable();        // nombre normalizado del comercio
$table->string('category')->nullable();        // Enum TransactionCategory (nullable en MVP)
$table->timestamps();

$table->index(['account_id', 'date']);
$table->index(['statement_id']);
```

### `anomalies`
```php
$table->id();
$table->foreignId('account_id')->constrained()->cascadeOnDelete();
$table->foreignId('statement_id')->nullable()->constrained()->cascadeOnDelete();
$table->string('type');        // duplicate_charge | charge_burst | recurring_subscription | reversal | unusual_amount | possible_fraud
$table->string('severity');    // low | medium | high
$table->string('title');
$table->text('description');
$table->decimal('amount', 15, 2)->nullable();     // monto total implicado
$table->json('transaction_ids')->nullable();      // ids de transacciones involucradas
$table->json('metadata')->nullable();
$table->string('status')->default('open');        // open | dismissed | resolved
$table->timestamps();
```

### Modelos y relaciones
- `User` hasMany `Account`
- `Account` belongsTo `User`; hasMany `Statement`; hasMany `Transaction`; hasMany `Anomaly`
- `Statement` belongsTo `Account`; hasMany `Transaction`; hasMany `Anomaly`
- `Transaction` belongsTo `Statement`, `Account`
- `Anomaly` belongsTo `Account`, `Statement`

Crear **factories** para todos (con estados útiles, p. ej. `Statement::factory()->reconciled()` y `->needsReview()`). Crear un **seeder de bancos conocidos** (ver Fase 1).

---

## 5. Fases de implementación

### Fase 0 — Configuración base
- Publicar config de IA si aplica (`php artisan vendor:publish` del provider de `laravel/ai`, revisar con el skill).
- `.env`: agregar `ANTHROPIC_API_KEY=` y el provider/modelo por defecto. Documentar en `.env.example` (sin la clave real).
- Verificar que la **cola** funcione (el script `composer run dev` ya levanta `queue:listen`). Para MVP puede usarse `QUEUE_CONNECTION=database` (crear tabla de jobs con `php artisan make:queue-table && php artisan migrate`) o `sync` si se prefiere procesar inline; **preferir `database`** para que la subida sea asíncrona.
- Configurar **disco privado** para PDFs (usar el disco `local` en `storage/app/private`, que ya existe en Laravel 13).

**DoD:** `php artisan test` pasa; un tinker/test mínimo confirma que un agente `laravel/ai` responde (o su `fake`).

---

### Fase 1 — Cuentas (CRUD)
- `php artisan make:controller AccountController --resource` (usar solo index/create/store/show/destroy en MVP; edit/update opcional).
- Rutas en `routes/web.php` dentro del grupo `['auth','verified']`:
  ```php
  Route::resource('accounts', AccountController::class)->only(['index','store','show','destroy']);
  ```
- **Policy** `AccountPolicy` — el usuario solo ve/gestiona sus cuentas (scope `user_id`). Registrar y usar `$this->authorize()`.
- **Lista de bancos conocidos**: crear `App\Enums\Bank` o un `config/banks.php` con una lista curada (Wells Fargo, Bank of America, Chase, Citi, Capital One, US Bank, PNC, TD Bank, Truist, Discover, American Express, + bancos LatAm comunes) y permitir "Otro" con texto libre. Exponerla a React como prop.
- **Páginas React**:
  - `resources/js/pages/accounts/index.tsx` — grid de cuentas (usar `Card`), botón "Nueva cuenta" (abre `Dialog` con form: nombre, banco (`Select`), tipo, últimos 4). Empty state amable si no hay cuentas.
  - `resources/js/pages/accounts/show.tsx` — detalle de la cuenta: encabezado + lista de statements + botón "Subir estado de cuenta".
- **Sidebar**: agregar ítem "Cuentas" en `app-sidebar.tsx` (icono `lucide-react` p. ej. `Wallet`).

**DoD + tests Pest:**
- `it('creates an account for the authenticated user')`
- `it('lists only the current user accounts')`
- `it('forbids viewing another users account')` (policy)

---

### Fase 2 — Subida de estados de cuenta
- `php artisan make:controller StatementController` con `store` (subida) y `show`.
- Rutas:
  ```php
  Route::post('accounts/{account}/statements', [StatementController::class, 'store'])->name('statements.store');
  Route::get('statements/{statement}', [StatementController::class, 'show'])->name('statements.show');
  ```
- `store`:
  - **FormRequest** `StoreStatementRequest`: `file` → `required|file|mimetypes:application/pdf|max:20480` (20 MB).
  - Autorizar que la `account` sea del usuario.
  - Guardar el archivo: `$path = $request->file('file')->store("statements/{$account->id}", 'local');`
  - Crear `Statement` (status `pending`, `original_filename`, `file_path`).
  - `ProcessStatement::dispatch($statement);`
  - Redirect a `statements.show` con toast "Procesando…".
- **UI de subida**: en `accounts/show.tsx`, un `Dialog` o zona drag-&-drop con `<input type="file" accept="application/pdf">`. La cuenta ya está fijada por el contexto (no se pide banco de nuevo — esa es la ventaja del modelo de Cuentas).
- **Estado en vivo**: `statements/show.tsx` debe mostrar el `status`. Para el MVP basta **polling** de Inertia (`router.reload` cada ~4s mientras `status ∈ {pending, processing}`), o el helper de *polling* de Inertia v2/v3. Mostrar **skeleton** mientras procesa.

**DoD + tests:**
- `it('stores an uploaded pdf and dispatches the processing job')` (usar `Storage::fake('local')` + `Queue::fake()` + `UploadedFile::fake()->create('e.pdf', 100, 'application/pdf')`).
- `it('rejects non-pdf uploads')`.
- `it('prevents uploading to another users account')`.

---

### Fase 3 — Extracción con IA
Ver **§6** para el código completo del agente. Resumen:
- `php artisan make:agent StatementExtractor --structured`
- Define `instructions()` (rol: extractor experto de estados de cuenta; reglas: montos positivos + `direction`; fechas ISO; no inventar; si un dato no está, `null`) y `schema()` (ver §6).
- El Job `ProcessStatement` llama al agente pasando el PDF como **attachment**:
  ```php
  $response = (new StatementExtractor)->prompt(
      'Extrae todos los datos y movimientos de este estado de cuenta.',
      attachments: [ \Laravel\Ai\Files\Document::fromStorage($statement->file_path, disk: 'local') ],
  );
  ```

**DoD + tests:** con `StatementExtractor::fake([...])` devolviendo un JSON fijo (usar los datos reales del Wells Fargo de marzo como fixture), el job crea el `Statement` con sus `transactions`. `assertPrompted`.

---

### Fase 4 — Reconciliación (validador de saldos)
Ver **§7** para el código. Es la **capa crítica anti-error de la IA**.
- Servicio `StatementReconciler::reconcile(array $extracted): ReconResult`.
- Comprueba: `beginning + Σcredits − Σdebits ≈ ending` (tolerancia `0.01`); y `total_deposits ≈ Σcredits`, `total_withdrawals ≈ Σdebits`.
- Si cuadra → `is_reconciled = true`, `status = processed`.
- Si no → `status = needs_review`, guarda `reconciliation_diff`. **Igual se guardan** las transacciones (para que el usuario pueda revisar), pero la UI marca el statement como "requiere revisión".

**DoD + tests:**
- `it('marks a statement as reconciled when balances add up')` (fixture Wells Fargo marzo: begin 9,166.13 / deposits 16,652.72 / withdrawals 16,152.44 / end 9,666.41).
- `it('flags needs_review when balances do not add up')` (fixture manipulado).

---

### Fase 5 — Detección de anomalías
Ver **§8**. Corre **después** de reconciliar, sobre transacciones ya guardadas.
- Servicio `AnomalyDetector::detect(Statement $statement): void` que crea filas en `anomalies`.
- **Reglas deterministas** (baratas y confiables) primero; luego **una pasada de IA** opcional que explique/priorice en lenguaje natural (agente `AnomalyExplainer --structured`).

**DoD + tests:**
- `it('detects a charge burst')` — dataset con 44 cargos del mismo comercio el mismo día ⇒ 1 anomalía `charge_burst` de severidad alta.
- `it('detects duplicate charges')`.
- `it('links reversals to original charges')`.

---

### Fase 6 — Dashboard y vistas
- Reemplazar el placeholder de `resources/js/pages/dashboard.tsx` por widgets reales:
  - **Tarjetas resumen**: total ingresos / total gastos / saldo neto del periodo seleccionado (o agregados por cuenta).
  - **Gasto por comercio** (top 10) — tabla o barras simples con divs Tailwind (sin librería de charts en MVP salvo que ya exista).
  - **Alertas de anomalías abiertas** — lista con severidad (`Badge`).
  - Selector de cuenta (si hay varias).
- `DashboardController` que arma los agregados con Eloquent (¡cuidado con N+1; usar `withSum`, `groupBy`).
- `statements/show.tsx`: tabla de transacciones (fecha, descripción, comercio, monto con color según `direction`, saldo corriente), banner de estado (`processed` / `needs_review` con el diff), y sección de anomalías del statement.

**DoD + tests:** `DashboardController` devuelve props correctas; test de agregados (gasto por comercio) con dataset conocido.

---

### Fase 7 — Seguridad y pulido
Ver **§10**. Policies en todo, archivos privados, opción de borrar PDF, rate limit en subida, no loguear contenido del PDF. Pint + types:check + suite completa verde.

---

## 6. Detalle: Agente `StatementExtractor`

`app/Ai/Agents/StatementExtractor.php` (tras `make:agent ... --structured`):

```php
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class StatementExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Eres un experto en extraer datos de estados de cuenta bancarios (bank statements) en PDF.
        Extrae la información EXACTA que aparece en el documento. Reglas:
        - NO inventes ni calcules valores. Si un dato no aparece, devuélvelo como null.
        - Cada transacción: "amount" SIEMPRE positivo, y "direction" = "credit" (depósito/entrada)
          o "debit" (retiro/salida).
        - "running_balance" = el "ending daily balance" de esa línea si aparece; si no, null.
        - "reference" = el número de referencia del banco (Ref #, etc.) si aparece.
        - "merchant" = nombre corto y normalizado del comercio (ej. "FanDuel", "Mercari", "OpenAI").
        - Fechas en formato ISO "YYYY-MM-DD". Infiere el año del periodo del estado de cuenta.
        - Incluye TODAS las transacciones, incluidas devoluciones ("Purchase Return"),
          reversos ("Reversal") y créditos provisionales ("Provisional Credit").
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bank_name'          => $schema->string()->nullable(),
            'account_last_four'  => $schema->string()->nullable(),
            'period_start'       => $schema->string()->nullable(), // YYYY-MM-DD
            'period_end'         => $schema->string()->nullable(),
            'beginning_balance'  => $schema->number()->nullable(),
            'ending_balance'     => $schema->number()->nullable(),
            'total_deposits'     => $schema->number()->nullable(),
            'total_withdrawals'  => $schema->number()->nullable(),
            'transactions'       => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'date'            => $schema->string()->required(),   // YYYY-MM-DD
                    'description'     => $schema->string()->required(),
                    'amount'          => $schema->number()->required(),   // positivo
                    'direction'       => $schema->string()->enum(['credit', 'debit'])->required(),
                    'running_balance' => $schema->number()->nullable(),
                    'reference'       => $schema->string()->nullable(),
                    'merchant'        => $schema->string()->nullable(),
                ])
            )->required(),
        ];
    }
}
```

> **Nota:** confirma con el skill `ai-sdk-development` los métodos exactos del `JsonSchema` (`number()` vs `float()`, `nullable()` vs no-`required()`). Ajusta si la API v0.9 difiere. El **modelo/proveedor** por defecto se configura en `config/ai.php`; para forzarlo por prompt: `->prompt(..., provider: Lab::Anthropic, model: 'claude-sonnet-4-6')`.

### Job `ProcessStatement`
`php artisan make:job ProcessStatement`:
```php
public function handle(StatementReconciler $reconciler, AnomalyDetector $detector): void
{
    $this->statement->update(['status' => StatementStatus::Processing]);

    try {
        $response = (new StatementExtractor)->prompt(
            'Extrae todos los datos y movimientos de este estado de cuenta.',
            attachments: [ Document::fromStorage($this->statement->file_path, disk: 'local') ],
        );

        $data = $response->toArray(); // StructuredAgentResponse → array

        DB::transaction(function () use ($data, $reconciler) {
            $this->persist($data);              // guarda header + transactions
            $reconciler->reconcile($this->statement->fresh()); // set status/diff
        });

        $detector->detect($this->statement->fresh());

        if (config('centavo.delete_pdf_after_processing')) {
            Storage::disk('local')->delete($this->statement->file_path);
            $this->statement->update(['file_path' => null]);
        }
    } catch (\Throwable $e) {
        $this->statement->update([
            'status' => StatementStatus::Failed,
            'failure_reason' => $e->getMessage(),
        ]);
        report($e);
        throw $e;
    }
}
```
Configurar `tries`, `backoff`, y `timeout` (>= 120s) en el job.

---

## 7. Detalle: `StatementReconciler`

`app/Services/StatementReconciler.php`:
```php
class StatementReconciler
{
    private const TOLERANCE = 0.01;

    public function reconcile(Statement $statement): void
    {
        $credits = $statement->transactions->where('direction', TransactionDirection::Credit)->sum('amount');
        $debits  = $statement->transactions->where('direction', TransactionDirection::Debit)->sum('amount');

        $begin = (float) $statement->beginning_balance;
        $end   = (float) $statement->ending_balance;

        $expectedEnd = $begin + $credits - $debits;
        $diff = round($expectedEnd - $end, 2);

        $balances_ok  = abs($diff) <= self::TOLERANCE;
        $deposits_ok  = abs($credits - (float) $statement->total_deposits) <= self::TOLERANCE;
        $withdraw_ok  = abs($debits - (float) $statement->total_withdrawals) <= self::TOLERANCE;

        $reconciled = $balances_ok && $deposits_ok && $withdraw_ok;

        $statement->update([
            'is_reconciled'        => $reconciled,
            'reconciliation_diff'  => $diff,
            'status'               => $reconciled ? StatementStatus::Processed : StatementStatus::NeedsReview,
            'processed_at'         => now(),
        ]);
    }
}
```
> Opcional (mejora de confianza): validar fila por fila que `running_balance[i] == running_balance[i-1] ± amount[i]` cuando el saldo corriente esté presente, y marcar las filas que rompan la cadena.

---

## 8. Detalle: Detección de anomalías

`app/Services/AnomalyDetector.php` — reglas deterministas sobre `$statement->transactions`:

1. **Charge burst (ráfaga)**: agrupar `debit` por `(merchant, date)`. Si `count >= 5` para un mismo comercio en un día ⇒ anomalía `charge_burst`, severidad `high`, `amount` = suma, `transaction_ids` = todas. *(Caso FanDuel: 44 cargos de $100 el 3/6.)*
2. **Duplicate charge**: mismo `(merchant, amount, date)` con `count > 1` y que **no** sea parte de un burst ⇒ `duplicate_charge`, severidad `medium`.
3. **Reversal / chargeback**: transacciones cuya descripción contenga `Purchase Return`, `Reversal`, `Provisional Credit`, o `direction=credit` que casen por `reference` con un `debit` previo ⇒ `reversal`, severidad `low` (informativo: "te devolvieron X").
4. **Recurring subscription**: mismo `merchant` con montos similares (±5%) que aparezca en periodos separados ~mensualmente (requiere histórico entre statements de la cuenta) ⇒ `recurring_subscription`, `low`. *(En MVP, con 1-2 statements, puede quedar básico.)*
5. **Unusual amount**: `debit` cuyo monto supere N desviaciones del promedio de la cuenta ⇒ `unusual_amount`, `medium`.

**Pasada de IA (opcional, recomendada):** agente `AnomalyExplainer --structured` que recibe un **resumen compacto** de las transacciones (no el PDF) y devuelve una lista de hallazgos en lenguaje natural con `{ type, severity, title, description, transaction_indexes }`. Fusionar con las reglas (deduplicar). Esto captura patrones que las reglas no ven y produce descripciones amables para el usuario.

> Ojo: la IA aquí **explica y clasifica**, no decide montos. Los montos salen de las transacciones ya validadas.

---

## 9. UI / páginas (resumen)

Crear en `resources/js/pages/`:
- `accounts/index.tsx` — grid de cuentas + Dialog "Nueva cuenta".
- `accounts/show.tsx` — cuenta + statements + subir PDF.
- `statements/show.tsx` — resumen del statement, banner de estado, tabla de transacciones, anomalías.
- `dashboard.tsx` — reescribir con widgets reales.

Reusar componentes de `components/ui/`. Para la **tabla**, si no existe `ui/table.tsx`, créalo con shadcn (estructura estándar `Table/TableHeader/TableRow/TableCell`). Toasts con `sonner` (ya está el `<Toaster/>` en `app.tsx`). Estados de carga con `ui/skeleton.tsx`. **Colores neutros** (gris/negro/blanco) como línea base; acentos con moderación (ver preferencia del usuario por UI neutra). Montos: `credit` en verde, `debit` en neutro/rojo tenue.

Agregar a `mainNavItems` en `app-sidebar.tsx`: **Dashboard**, **Cuentas**.

---

## 10. Seguridad (datos financieros)

- **Autorización**: `AccountPolicy` + verificación en `StatementController` (la cuenta/statement debe pertenecer al usuario). Nunca confiar en IDs del request sin `authorize`.
- **Archivos privados**: PDFs en `storage/app/private/statements/...` (disco `local`, nunca `public`). Servir descargas solo vía ruta autorizada si se necesita.
- **Retención**: config `centavo.delete_pdf_after_processing` (default `true` en prod recomendado). Guardar solo los datos extraídos, no el PDF, salvo que el usuario opte por conservarlo.
- **No entrenar / no filtrar**: dejar claro (y en el prompt/config) que el contenido no se usa para entrenamiento; no loguear el contenido del PDF ni las transacciones en claro en logs.
- **Rate limit** en la subida (`throttle`) para evitar abuso de la API de IA.
- **Validación estricta** del upload (mimetype real, tamaño).
- `.env`: `ANTHROPIC_API_KEY` fuera del repo; documentar en `.env.example` vacío.

---

## 11. Testing (Pest v4)

- **Todo con factories.** Feature tests para cada fase (ver DoD por fase).
- **IA fakeada**: `StatementExtractor::fake([$fixtureArray])` para no gastar tokens. Guardar un fixture con los datos reales del Wells Fargo de marzo en `tests/Fixtures/wells_fargo_march.php` para probar reconciliación y burst de FanDuel de punta a punta.
- **Cola**: `Queue::fake()` para la subida; test aparte que ejecuta el job con el extractor fakeado.
- **Storage**: `Storage::fake('local')`.
- Cubrir happy path, fallos (PDF inválido, saldos que no cuadran) y autorización (usuario ajeno).
- Correr por archivo/filtro mientras desarrollas; al final, suite completa (`php artisan test`).

---

## 12. Criterios de aceptación (Definition of Done del MVP)

1. Un usuario autenticado crea una **cuenta** (nombre + banco + últimos 4) y la ve en su lista; no ve las de otros.
2. Sube un **PDF** de estado de cuenta a esa cuenta; la subida es asíncrona (job en cola) con feedback de estado.
3. La IA **extrae** los movimientos; PHP los **reconcilia** contra los saldos. Con los 2 PDFs reales de Wells Fargo, el statement queda `processed` y **cuadra** (diff 0.00).
4. Se **detectan anomalías** reales: la ráfaga de ~44 cargos de FanDuel aparece como `charge_burst`; las devoluciones aparecen como `reversal`.
5. El **dashboard** muestra totales, gasto por comercio y anomalías abiertas.
6. **Seguridad**: policies en su lugar, PDFs en disco privado, opción de borrado tras procesar.
7. `vendor/bin/pint` limpio, `npm run types:check` sin errores, **toda la suite Pest verde**.

---

## 13. Notas para el agente ejecutor

- Usa `php artisan make:*` para todo (models, controllers, jobs, agents, tests, enums, policies, requests). Pasa `--no-interaction`.
- Tras tocar PHP: `vendor/bin/pint --dirty`. Tras tocar rutas: regenerar Wayfinder (`npm run dev` o el comando de wayfinder).
- Activa los **skills** indicados en §1 antes de cada dominio; usa `search-docs` para la API exacta de `laravel/ai` v0.9, Inertia v3 y Fortify cuando dudes.
- Respeta las convenciones del starter kit (Wayfinder, `<Form>`, layouts por convención, pages en minúscula, shadcn/ui, Tailwind v4).
- Trabaja **por fases** y deja los tests de cada fase en verde antes de seguir.
- Comunícate en **español**.
- **No agregues features fuera del alcance** (§0). Ante una decisión de producto ambigua, pregunta al usuario.
- Al terminar, guarda un resumen en `memory/resumenes/YYYY-MM-DD-mvp-centavo.md` (regla global del usuario).
```
