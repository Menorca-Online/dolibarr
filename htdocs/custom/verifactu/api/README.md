# API Verifactu - Validación NIF AEAT

## Endpoint: validate-nif-aeat

### URL
```
POST /custom/verifactu/api/validate-nif-aeat.php
```

### Descripción
Valida un NIF/CIF/NIE contra el servicio web oficial de la AEAT (Agencia Estatal de Administración Tributaria).

### Autenticación
Requiere autenticación de usuario con permisos del módulo Verifactu.

### Request

#### Headers
```
Content-Type: application/json
```

#### Body (JSON)
```json
{
    "nif": "12345678Z",
    "nombre": "NOMBRE APELLIDOS"
}
```

#### Parámetros
- `nif` (string, requerido): NIF/CIF/NIE a validar (máximo 15 caracteres)
- `nombre` (string, requerido): Nombre del contribuyente (máximo 300 caracteres)

### Response

#### Éxito (200 OK)
```json
{
    "valido": true,
    "validacion_local": true,
    "validacion_aeat": true,
    "nif": "12345678Z",
    "nombre": "NOMBRE APELLIDOS"
}
```

#### Error de formato local (200 OK)
```json
{
    "valido": false,
    "error": "Formato de NIF/CIF/NIE inválido.",
    "validacion_local": false,
    "validacion_aeat": null
}
```

#### Error de validación (400 Bad Request)
```json
{
    "error": "Los campos \"nif\" y \"nombre\" son obligatorios."
}
```

#### Error de permisos (401 Unauthorized)
```json
{
    "error": "No tiene permisos para acceder a esta API."
}
```

#### Error del servidor (500 Internal Server Error)
```json
{
    "error": "Error interno del servidor.",
    "mensaje": "Error al procesar la validación."
}
```

### Campos de respuesta

- `valido`: Boolean indicando si la validación completa es exitosa
- `validacion_local`: Boolean indicando si el formato es válido localmente
- `validacion_aeat`: Boolean indicando si es válido según AEAT (null si no se llegó a validar)
- `nif`: NIF proporcionado en la request
- `nombre`: Nombre proporcionado en la request
- `error`: Mensaje de error cuando corresponde

### Ejemplos de uso

#### Usando cURL
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"nif":"12345678Z","nombre":"JUAN PÉREZ GARCÍA"}' \
  http://your-domain/custom/verifactu/api/validate-nif-aeat.php
```

#### Usando JavaScript
```javascript
fetch('/custom/verifactu/api/validate-nif-aeat.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        nif: '12345678Z',
        nombre: 'JUAN PÉREZ GARCÍA'
    })
})
.then(response => response.json())
.then(data => {
    console.log('Resultado:', data);
});
```

### Notas importantes

1. **Certificados**: Requiere certificados válidos de la AEAT en `/custom/verifactu/certs/`
2. **Validación en dos etapas**: Primero valida formato local, luego consulta AEAT
3. **Logging**: Todas las operaciones se registran en el log de Dolibarr
4. **Timeout**: Timeout de 30 segundos para la consulta AEAT
5. **Seguridad**: Requiere autenticación y permisos del módulo Verifactu