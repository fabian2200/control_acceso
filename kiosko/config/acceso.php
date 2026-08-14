<?php

return [
    'hora_entrada' => env('ACCESO_HORA_ENTRADA', '08:00'),
    'terminal_codigo' => env('ACCESO_TERMINAL', 'REC-01'),
    'auto_return_seconds' => (int) env('ACCESO_AUTO_RETURN', 4),
    'ubicacion' => env('ACCESO_UBICACION', 'Recepción · Torre Norte'),
];
