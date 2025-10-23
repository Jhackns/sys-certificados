# Guía de Marcadores de Posición para Diseños de Canva

## Introducción

Al crear diseños de certificados en Canva, puedes utilizar marcadores de posición específicos que el sistema reemplazará automáticamente con la información real del certificado cuando se genere.

## Marcadores Disponibles

Utiliza los siguientes marcadores en tu diseño de Canva:

| Marcador | Descripción | Ejemplo |
|----------|-------------|---------|
| `{{nombreCompleto}}` | Nombre completo del usuario | Juan Pérez González |
| `{{fechaEmision}}` | Fecha de emisión del certificado | 15/07/2024 |
| `{{codigoQR}}` | Código QR para verificación | [Imagen de QR] |

## Cómo Usar los Marcadores

1. Crea tu diseño en Canva
2. Para texto: Añade cuadros de texto con los marcadores exactamente como están escritos arriba, incluyendo las llaves dobles
3. Para el código QR: Añade un marcador de imagen y usa el texto `{{codigoQR}}`

## Ejemplo

- Para incluir el nombre del usuario: Añade un cuadro de texto con `{{nombreCompleto}}`
- Para incluir la fecha de emisión: Añade un cuadro de texto con `{{fechaEmision}}`
- Para incluir el código QR: Añade un marcador de imagen con `{{codigoQR}}`

## Notas Importantes

- Asegúrate de escribir los marcadores exactamente como se muestran, respetando mayúsculas y minúsculas
- No modifiques el formato de los marcadores (mantén las llaves dobles)
- El sistema reemplazará automáticamente estos marcadores con la información real cuando se genere el certificado
