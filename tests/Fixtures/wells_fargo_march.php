<?php

/*
|--------------------------------------------------------------------------
| Fixture: Wells Fargo — estado de cuenta de marzo
|--------------------------------------------------------------------------
|
| Reproduce la salida estructurada esperada del agente StatementExtractor
| para el caso real que originó la idea: una ráfaga de 44 cargos de FanDuel
| de $100 el mismo día, dos cargos de Mercari, y sus devoluciones/créditos
| provisionales.
|
| Los totales cuadran de forma determinista:
|   begin 9,166.13 + depósitos 16,652.72 − retiros 16,152.44 = end 9,666.41
|
*/

$transactions = [];

// --- Débitos (salidas) ---
$transactions[] = ['date' => '2025-03-01', 'description' => 'ONLINE TRANSFER TO LANDLORD RENT', 'amount' => 2500.00, 'direction' => 'debit', 'running_balance' => null, 'reference' => null, 'merchant' => 'Landlord'];
$transactions[] = ['date' => '2025-03-05', 'description' => 'PURCHASE AUTHORIZED WHOLE FOODS MARKET', 'amount' => 812.50, 'direction' => 'debit', 'running_balance' => null, 'reference' => null, 'merchant' => 'Whole Foods'];

// Ráfaga de FanDuel: 44 cargos de $100 el 2025-03-06.
for ($i = 1; $i <= 44; $i++) {
    $transactions[] = [
        'date' => '2025-03-06',
        'description' => 'PURCHASE AUTHORIZED ON 03/06 FANDUEL',
        'amount' => 100.00,
        'direction' => 'debit',
        'running_balance' => null,
        'reference' => 'FD'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'merchant' => 'FanDuel',
    ];
}

// Dos cargos de Mercari.
$transactions[] = ['date' => '2025-03-10', 'description' => 'PURCHASE AUTHORIZED MERCARI', 'amount' => 2769.97, 'direction' => 'debit', 'running_balance' => null, 'reference' => 'MK000001', 'merchant' => 'Mercari'];
$transactions[] = ['date' => '2025-03-10', 'description' => 'PURCHASE AUTHORIZED MERCARI', 'amount' => 2769.97, 'direction' => 'debit', 'running_balance' => null, 'reference' => 'MK000002', 'merchant' => 'Mercari'];

$transactions[] = ['date' => '2025-03-12', 'description' => 'PURCHASE AUTHORIZED AMAZON.COM', 'amount' => 1500.00, 'direction' => 'debit', 'running_balance' => null, 'reference' => null, 'merchant' => 'Amazon'];
$transactions[] = ['date' => '2025-03-15', 'description' => 'PG&E UTILITIES PAYMENT', 'amount' => 400.00, 'direction' => 'debit', 'running_balance' => null, 'reference' => null, 'merchant' => 'PG&E'];
$transactions[] = ['date' => '2025-03-18', 'description' => 'GEICO CAR INSURANCE', 'amount' => 1000.00, 'direction' => 'debit', 'running_balance' => null, 'reference' => null, 'merchant' => 'Geico'];

// --- Créditos (entradas) ---
$transactions[] = ['date' => '2025-03-01', 'description' => 'DIRECT DEPOSIT PAYROLL', 'amount' => 3000.00, 'direction' => 'credit', 'running_balance' => null, 'reference' => null, 'merchant' => 'Employer'];
$transactions[] = ['date' => '2025-03-16', 'description' => 'DIRECT DEPOSIT PAYROLL', 'amount' => 3000.00, 'direction' => 'credit', 'running_balance' => null, 'reference' => null, 'merchant' => 'Employer'];
$transactions[] = ['date' => '2025-03-18', 'description' => 'MOBILE DEPOSIT', 'amount' => 2000.00, 'direction' => 'credit', 'running_balance' => null, 'reference' => null, 'merchant' => null];
$transactions[] = ['date' => '2025-03-22', 'description' => 'ZELLE FROM JOHN DOE', 'amount' => 1482.75, 'direction' => 'credit', 'running_balance' => null, 'reference' => null, 'merchant' => null];
$transactions[] = ['date' => '2025-03-25', 'description' => 'PROVISIONAL CREDIT FANDUEL DISPUTE', 'amount' => 4400.00, 'direction' => 'credit', 'running_balance' => null, 'reference' => null, 'merchant' => 'FanDuel'];
$transactions[] = ['date' => '2025-03-28', 'description' => 'PURCHASE RETURN MERCARI', 'amount' => 2769.97, 'direction' => 'credit', 'running_balance' => null, 'reference' => 'MK000002', 'merchant' => 'Mercari'];

return [
    'bank_name' => 'Wells Fargo',
    'account_last_four' => '4821',
    'period_start' => '2025-03-01',
    'period_end' => '2025-03-31',
    'beginning_balance' => 9166.13,
    'ending_balance' => 9666.41,
    'total_deposits' => 16652.72,
    'total_withdrawals' => 16152.44,
    'transactions' => $transactions,
];
