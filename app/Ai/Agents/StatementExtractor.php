<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[MaxTokens(16384)]
#[Timeout(300)]
class StatementExtractor implements Agent
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
     *
     * The response is plain JSON (parsed in ProcessStatement) rather than the
     * provider's strict structured-output mode, whose grammar compilation times
     * out on this schema.
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

        $instructions .= "\n\n".<<<'JSON'
        Responde ÚNICAMENTE con un objeto JSON válido, sin markdown, sin ```, y sin texto antes o
        después. Usa EXACTAMENTE esta forma:
        {
          "bank_name": string|null,
          "account_last_four": string|null,
          "period_start": "YYYY-MM-DD"|null,
          "period_end": "YYYY-MM-DD"|null,
          "beginning_balance": number|null,
          "ending_balance": number|null,
          "total_deposits": number|null,
          "total_withdrawals": number|null,
          "transactions": [
            {
              "date": "YYYY-MM-DD",
              "description": string,
              "amount": number,
              "direction": "credit"|"debit",
              "running_balance": number|null,
              "reference": string|null,
              "merchant": string|null,
              "category": string
            }
          ]
        }
        JSON;

        return $instructions;
    }
}
