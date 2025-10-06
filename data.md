1. Sobre la distribución proporcional del anticipo: Si tengo esta cotización:
Equipo 1: costoBase = $100,000 (cantidad: 1)
Equipo 2: costoBase = $200,000 (cantidad: 1)
Total costoBase: $300,000
Anticipo: $30,000
¿La distribución sería así?
Anticipo para Equipo 1: $30,000 × ($100,000 / $300,000) = $10,000
Anticipo para Equipo 2: $30,000 × ($200,000 / $300,000) = $20,000

Sí.

2. Sobre el flujo del cálculo: Actualmente el flujo es:
// Sin anticipo:
costoBase = costo + GPS + Placas  // ej: $1,007,500
costoEquipo = costoBase * margen   // ej: $1,279,525 (margen 1.27)
Con anticipo, ¿debe ser así?
// Con anticipo:
costoBase = costo + GPS + Placas           // ej: $1,007,500
anticipoProporcional = calcularProporcional() // ej: $10,000
costoBaseConAnticipo = costoBase - anticipoProporcional // ej: $997,500
costoEquipo = costoBaseConAnticipo * margen  // ej: $1,266,825

Correcto.

3. Sobre el seguro: 
¿El seguro se debe calcular sobre el costoBase original o sobre el costoBase - anticipo? 

Opción B: seguro = (costoBase - anticipo) * tarifaSeguro (sobre monto con anticipo descontado) 

4. Sobre cuándo calcular el anticipo: ¿El anticipo debe calcularse y distribuirse:

Opción B: En wizard.js después de calcular todos los equipos (más simple, solo ajusta los resultados finales)

