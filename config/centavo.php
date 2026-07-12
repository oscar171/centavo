<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelo de IA
    |--------------------------------------------------------------------------
    |
    | Modelo de Anthropic usado para la extracción de estados de cuenta y la
    | explicación de anomalías. "claude-sonnet-5" ofrece el mejor balance de
    | velocidad e inteligencia; se puede subir a "claude-opus-4-8" si se
    | necesita más precisión a costa de un mayor costo.
    |
    */

    'ai_model' => env('CENTAVO_AI_MODEL', 'claude-sonnet-5'),

    /*
    |--------------------------------------------------------------------------
    | Retención del PDF
    |--------------------------------------------------------------------------
    |
    | Cuando está activo, el PDF original se elimina del disco privado tras
    | procesarlo correctamente, conservando solo los datos extraídos. En
    | producción se recomienda "true"; en local se deja "false" para poder
    | reprocesar durante el desarrollo.
    |
    */

    'delete_pdf_after_processing' => env('CENTAVO_DELETE_PDF_AFTER_PROCESSING', false),

];
