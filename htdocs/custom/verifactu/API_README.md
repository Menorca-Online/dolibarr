# API de Validación Verifactu

## Descripción

La API de validación de Verifactu proporciona endpoints para validar NIFs, CIFs y NIEs tanto localmente como contra los servicios de la AEAT.

## Estructura de Archivos

```
htdocs/custom/verifactu/class/
├── api_verifactu.class.php          # Clase API principal (VerifactuApi)
└── verifactuaeatvalidator.class.php # Utilidad de validación (VerifactuAEATValidator)
```

## Endpoints Disponibles

### 1. Validación contra AEAT
**POST** `/api/index.php/verifactu/validate-nif-aeat`

Valida un NIF/CIF/NIE contra los servicios web de la AEAT.

**Headers requeridos:**
```
DOLAPIKEY: tu_api_key_de_dolibarr
Content-Type: application/json
```

**Body (JSON):**
```json
{
    "nif": "12345678A",
    "nombre": "Nombre del Contribuyente"
}
```

**Respuesta exitosa (200):**
```json
{
    "valid": true,
    "document": "12345678A",
    "name": "Nombre del Contribuyente",
    "source": "aeat",
    "message": "Documento válido según AEAT"
}
```

**Respuesta con error (400):**
```json
{
    "error": {
        "code": 400,
        "message": "NIF y nombre son requeridos"
    }
}
```

### 2. Validación Local
**GET** `/api/index.php/verifactu/validate-local/{document}`

Valida un documento solo con algoritmos locales (sin conexión a AEAT).

**Headers requeridos:**
```
DOLAPIKEY: tu_api_key_de_dolibarr
```

**Parámetros:**
- `{document}`: El NIF/CIF/NIE a validar

**Ejemplo:**
```
GET /api/index.php/verifactu/validate-local/12345678A
```

**Respuesta exitosa (200):**
```json
{
    "valid": true,
    "document": "12345678A",
    "format_valid": true,
    "checksum_valid": true,
    "type": "NIF/DNI",
    "source": "local"
}
```

## Ejemplos de Uso

### Con cURL

```bash
# Validación contra AEAT
curl -X POST 'http://localhost/dolibarr/api/index.php/verifactu/validate-nif-aeat' \
     -H 'DOLAPIKEY: tu_api_key' \
     -H 'Content-Type: application/json' \
     -d '{"nif":"12345678A","nombre":"Test User"}'

# Validación local
curl -X GET 'http://localhost/dolibarr/api/index.php/verifactu/validate-local/12345678A' \
     -H 'DOLAPIKEY: tu_api_key'
```

### Con JavaScript

```javascript
// Validación contra AEAT
const response = await fetch('/api/index.php/verifactu/validate-nif-aeat', {
    method: 'POST',
    headers: {
        'DOLAPIKEY': 'tu_api_key',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        nif: '12345678A',
        nombre: 'Test User'
    })
});
const result = await response.json();

// Validación local
const localResponse = await fetch('/api/index.php/verifactu/validate-local/12345678A', {
    headers: {
        'DOLAPIKEY': 'tu_api_key'
    }
});
const localResult = await localResponse.json();
```

## Códigos de Error

- **400 Bad Request**: Parámetros inválidos o faltantes
- **403 Forbidden**: Módulo Verifactu no habilitado o API key inválida
- **404 Not Found**: Endpoint no encontrado
- **500 Internal Server Error**: Error interno del servidor o problema con AEAT

## Configuración

1. **Módulo habilitado**: Asegúrate de que el módulo Verifactu esté habilitado en Dolibarr
2. **API key**: Configura una API key válida en Dolibarr (Configuración > API/Web services)
3. **Certificados**: Para validación AEAT, asegúrate de que los certificados estén configurados en `$DOL_DATA_ROOT/admin/temp/verifactu/`

## Integración con Código Existente

Para usar la validación en otros módulos o funciones:

```php
// Cargar la clase validadora
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuaeatvalidator.class.php';

$validator = new VerifactuAEATValidator($db);

// Validación local
$result = $validator->validarCIFNIFNIEDNI('12345678A');

// Validación AEAT
$result = $validator->validateNIFAEAT('12345678A', 'Nombre Test');
```