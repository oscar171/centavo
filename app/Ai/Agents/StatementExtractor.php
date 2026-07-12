<?php

namespace App\Ai\Agents;

use App\Enums\TransactionCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[MaxTokens(8192)]
#[Timeout(120)]
class StatementExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<int, string>  $customCategories  Category names the user has
     *                                                already created, so the AI
     *                                                can reuse them instead of
     *                                                falling back to "other".
     */
    public function __construct(private array $customCategories = []) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $instructions = <<<'PROMPT'
        Eres un experto en extraer datos de estados de cuenta bancarios (bank statements) en PDF.
        Extrae la información EXACTA que aparece en el documento. Reglas:
        - NO inventes ni calcules valores. Si un dato no aparece, devuélvelo como null.
        - Cada transacción: "amount" SIEMPRE positivo, y "direction" = "credit" (depósito/entrada)
          o "debit" (retiro/salida).
        - "running_balance" = el "ending daily balance" de esa línea si aparece; si no, null.
        - "reference" = el número de referencia del banco (Ref #, etc.) si aparece.
        - "merchant" = nombre corto y normalizado del comercio (ej. "FanDuel", "Mercari", "OpenAI").
        - "category" = clasifica CADA transacción en UNA de estas categorías (usa el valor en inglés):
          income (depósitos/nómina/entradas), housing (renta/hipoteca), utilities (luz/agua/internet/teléfono),
          food (supermercado/restaurantes), transport (gasolina/uber/transporte), shopping (compras/retail),
          entertainment (streaming de video/juegos/eventos), subscriptions (suscripciones recurrentes),
          health (salud/farmacia/seguro médico), travel (vuelos/hoteles), transfers (transferencias entre cuentas),
          fees (comisiones/cargos bancarios), other (cualquier otra). Si no estás seguro, usa "other".
        - Fechas en formato ISO "YYYY-MM-DD". Infiere el año del periodo del estado de cuenta.
        - Incluye TODAS las transacciones, incluidas devoluciones ("Purchase Return"),
          reversos ("Reversal") y créditos provisionales ("Provisional Credit").
        PROMPT;

        if ($this->customCategories !== []) {
            $list = implode(', ', $this->customCategories);
            $instructions .= "\n- El usuario también creó estas categorías personalizadas; "
                ."prefiere una de ellas cuando aplique en lugar de \"other\": {$list}.";
        }

        return $instructions;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'bank_name' => $schema->string()->nullable(),
            'account_last_four' => $schema->string()->nullable(),
            'period_start' => $schema->string()->nullable(), // YYYY-MM-DD
            'period_end' => $schema->string()->nullable(),
            'beginning_balance' => $schema->number()->nullable(),
            'ending_balance' => $schema->number()->nullable(),
            'total_deposits' => $schema->number()->nullable(),
            'total_withdrawals' => $schema->number()->nullable(),
            'transactions' => $schema->array()->items(
                $schema->object(fn ($schema) => [
                    'date' => $schema->string()->required(), // YYYY-MM-DD
                    'description' => $schema->string()->required(),
                    'amount' => $schema->number()->required(), // positivo
                    'direction' => $schema->string()->enum(['credit', 'debit'])->required(),
                    'running_balance' => $schema->number()->nullable(),
                    'reference' => $schema->string()->nullable(),
                    'merchant' => $schema->string()->nullable(),
                    'category' => $schema->string()->enum($this->allowedCategories())->required(),
                ])
            )->required(),
        ];
    }

    /**
     * The category values the AI may choose from: the predefined catalog plus
     * any custom categories the user has already created.
     *
     * @return array<int, string>
     */
    private function allowedCategories(): array
    {
        return array_values(array_unique(array_merge(
            TransactionCategory::values(),
            array_filter($this->customCategories, fn (string $category): bool => $category !== ''),
        )));
    }
}
