<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CanvaValidationService;

class TestCanvaValidation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'canva:test-validation {design_id? : ID o URL del diseño de Canva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la validación de diseños de Canva';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== PRUEBA DE VALIDACIÓN DE DISEÑOS DE CANVA ===');
        
        $designId = $this->argument('design_id') ?? 'https://www.canva.com/design/DAG1T58gl7Y/_CpAJcLCz';
        
        $this->info("Validando: $designId");
        
        $service = new CanvaValidationService();
        
        // Primero probar la extracción de ID de URL
        $this->info("\n--- Extrayendo ID de URL ---");
        $extractedId = $service->extractDesignIdFromUrl($designId);
        
        if ($extractedId) {
            $this->info("✓ ID extraído de URL: $extractedId");
            $designId = $extractedId;
        } else {
            $this->info("✓ Usando ID directo: $designId");
        }
        
        // Luego validar el diseño
        $this->info("\n--- Validando diseño ---");
        $result = $service->validateDesignId($designId);
        
        $this->info("Estado: {$result['status']}");
        $this->info("Válido: " . ($result['valid'] ? 'Sí' : 'No'));
        $this->info("Mensaje: {$result['message']}");
        
        if (isset($result['data'])) {
            $this->info("\n--- Detalles del diseño ---");
            foreach ($result['data'] as $key => $value) {
                $this->info("$key: $value");
            }
        }
        
        // Probar diferentes escenarios
        $this->info("\n--- Pruebas adicionales ---");
        
        // URL inválida
        $this->info("\n1. URL inválida:");
        $invalidResult = $service->validateCanvaUrl('https://google.com');
        $this->info("   Válido: " . ($invalidResult['valid'] ? 'Sí' : 'No'));
        $this->info("   Mensaje: {$invalidResult['message']}");
        
        // ID inválido
        $this->info("\n2. ID inválido:");
        $invalidIdResult = $service->validateDesignId('ID_INVALIDO_123');
        $this->info("   Válido: " . ($invalidIdResult['valid'] ? 'Sí' : 'No'));
        $this->info("   Estado: {$invalidIdResult['status']}");
        $this->info("   Mensaje: {$invalidIdResult['message']}");
        
        $this->info("\n=== PRUEBA COMPLETADA ===");
    }
}