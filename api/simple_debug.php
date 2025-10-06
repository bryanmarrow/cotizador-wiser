<?php
header('Content-Type: application/json; charset=utf-8');

// Simple test data to verify the view switching works
$testData = [
    'status' => 'success',
    'message' => 'Test data loaded',
    'data' => [
        'cotizaciones' => [
            [
                'id' => 'TEST-001',
                'fechaFormateada' => '01/09/2025 10:30',
                'estado' => 'completada',
                'cliente' => 'Cliente de Prueba 1',
                'vendedor' => 'Vendedor Test',
                'totalFormateado' => '$150,000.00',
                'cantidadEquipos' => 2
            ],
            [
                'id' => 'TEST-002', 
                'fechaFormateada' => '01/09/2025 14:15',
                'estado' => 'borrador',
                'cliente' => 'Cliente de Prueba 2',
                'vendedor' => 'Vendedor Test',
                'totalFormateado' => '$275,500.00',
                'cantidadEquipos' => 3
            ]
        ],
        'paginacion' => [
            'pagina_actual' => 1,
            'total_paginas' => 1,
            'total_registros' => 2,
            'registros_por_pagina' => 10
        ]
    ]
];

echo json_encode($testData);
?>