<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * API REST para módulo Verifactu
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT . '/api/class/api.class.php';

/**
 * API class for Verifactu module
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class Verifactu extends DolibarrApi
{
    /**
     * Constructor
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Test endpoint
     *
     * @return array
     *
     * @url GET test
     */
    public function test()
    {
        return [
            'message' => 'Verifactu API is working!',
            'timestamp' => time(),
            'module_enabled' => isModEnabled('verifactu'),
            'user_id' => isset($this->user) ? $this->user->id : 'not_set'
        ];
    }

    /**
     * Validate NIF/CIF against AEAT
     *
     * @return array
     *
     * @url POST validate-nif-aeat
     */
    public function validateNifAeat()
    {
        // Get raw input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new RestException(400, 'Invalid JSON data');
        }
        
        $nif = isset($data['nif']) ? trim($data['nif']) : '';
        $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
        
        if (empty($nif)) {
            throw new RestException(400, 'Field "nif" is required');
        }
        
        if (empty($nombre)) {
            throw new RestException(400, 'Field "nombre" is required');
        }
        
        // Try to load validator class
        $validatorFile = DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuaeatvalidator.class.php';
        if (file_exists($validatorFile)) {
            require_once $validatorFile;
            
            try {
                $validator = new VerifactuAEATValidator($this->db);
                
                // Local validation first
                $localValid = $validator->validarCIFNIFNIEDNI($nif);
                
                if (!$localValid) {
                    return [
                        'valido' => false,
                        'error' => 'Invalid NIF/CIF/NIE format',
                        'validacion_local' => false,
                        'validacion_aeat' => null,
                        'nif' => $nif,
                        'nombre' => $nombre
                    ];
                }
                
                // AEAT validation
                $aeatValid = $validator->validateNIFAEAT($nif, $nombre);
                
                return [
                    'valido' => $aeatValid,
                    'validacion_local' => $localValid,
                    'validacion_aeat' => $aeatValid,
                    'nif' => $nif,
                    'nombre' => $nombre,
                    'timestamp' => time()
                ];
                
            } catch (Exception $e) {
                return [
                    'error' => 'Validation error: ' . $e->getMessage(),
                    'nif' => $nif,
                    'nombre' => $nombre,
                    'timestamp' => time()
                ];
            }
        } else {
            // Fallback: basic validation only
            $isValid = (strlen($nif) >= 8 && strlen($nif) <= 15);
            
            return [
                'message' => 'Basic validation only (validator class not found)',
                'nif' => $nif,
                'nombre' => $nombre,
                'basic_validation' => $isValid,
                'timestamp' => time()
            ];
        }
    }

    /**
     * Validate local format
     *
     * @param string $document Document to validate
     * @return array
     *
     * @url GET validate-local/{document}
     */
    public function validateLocalFormat($document)
    {
        if (empty($document)) {
            throw new RestException(400, 'Document parameter is required');
        }
        
        // Try to load validator class
        $validatorFile = DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuaeatvalidator.class.php';
        if (file_exists($validatorFile)) {
            require_once $validatorFile;
            
            try {
                $validator = new VerifactuAEATValidator($this->db);
                $isValid = $validator->validarCIFNIFNIEDNI($document);
                
                return [
                    'valido' => $isValid,
                    'documento' => $document,
                    'tipo' => $this->detectDocumentType($document),
                    'timestamp' => time()
                ];
                
            } catch (Exception $e) {
                return [
                    'error' => 'Validation error: ' . $e->getMessage(),
                    'documento' => $document,
                    'timestamp' => time()
                ];
            }
        } else {
            // Fallback: basic validation only
            $isValid = (strlen($document) >= 8 && strlen($document) <= 15);
            
            return [
                'message' => 'Basic validation only (validator class not found)',
                'documento' => $document,
                'basic_validation' => $isValid,
                'timestamp' => time()
            ];
        }
    }

    /**
     * Detect document type (NIF, NIE, CIF)
     *
     * @param string $doc Document to analyze
     * @return string Document type
     */
    private function detectDocumentType($doc)
    {
        $doc = strtoupper(trim($doc));
        
        if (preg_match('/^[0-9]{8}[A-Z]$/', $doc)) {
            return 'NIF/DNI';
        }
        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $doc)) {
            return 'NIE';
        }
        if (preg_match('/^[A-Z][0-9]{7}[A-Z0-9]$/', $doc)) {
            return 'CIF';
        }
        
        return 'Unknown';
    }
}