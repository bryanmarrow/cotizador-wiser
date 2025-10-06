// Controlador de Cálculos - Cálculos financieros y utilidades
class ControladorCalculos {
    constructor() {
        this.residualPorDefecto = 20; // 20% residual por defecto
        this.inicializar();
    }

    inicializar() {
        this.configurarEscuchadoresCalculos();
    }

    configurarEscuchadoresCalculos() {
        // Actualización de cálculos en tiempo real
        const camposCalculos = ['costo-equipo', 'cantidad-equipo'];
        
        camposCalculos.forEach(idCampo => {
            const campo = document.getElementById(idCampo);
            if (campo) {
                campo.addEventListener('input', () => this.actualizarCalculosEnVivo());
                campo.addEventListener('change', () => this.actualizarCalculosEnVivo());
            }
        });

        // Cambio de tipo de equipo para actualizar tarifa de seguro
        const tipoEquipo = document.getElementById('tipo-equipo');
        if (tipoEquipo) {
            tipoEquipo.addEventListener('change', () => this.actualizarCalculosEnVivo());
        }
    }

    actualizarCalculosEnVivo() {
        const costo = parseFloat(document.getElementById('costo-equipo')?.value) || 0;
        const cantidad = parseInt(document.getElementById('cantidad-equipo')?.value) || 1;
        
        // Obtener términos globales del asistente
        const residual = window.asistente?.datosCotizacion?.residualGlobal || this.residualPorDefecto;
        const plazo = window.asistente?.datosCotizacion?.plazoGlobal || 0;
        const tasa = window.asistente?.datosCotizacion?.tasa || 0;
        
        const tipoEquipo = document.getElementById('tipo-equipo')?.value;

        // 🚨 CRITICAL DEBUG: Trace rate sources for margin calculation issue
        console.log('🚨 [MARGIN ISSUE TRACE] Rate source tracking:', {
            'USER_ROLE': window.CURRENT_USER?.role,
            'RATE_FROM_ASISTENTE': window.asistente?.datosCotizacion?.tasa,
            'RATE_LOCAL_VARIABLE': tasa,
            'IS_RATES_EQUAL': (window.asistente?.datosCotizacion?.tasa === tasa),
            'PLAZO': plazo,
            'EXPECTED_MARGIN_FORMULA': `1 + (${plazo} * ${tasa}) = ${1 + (plazo * tasa)}`,
            'COSTO': costo,
            'FULL_ASISTENTE_OBJECT': window.asistente?.datosCotizacion
        });

        if (costo > 0 && plazo > 0 && window.asistente && tasa > 0) {
            const tarifaSeguro = this.obtenerTarifaSeguro(tipoEquipo);
            
            // 🚨 CRITICAL: Double-check rate before calculation
            console.log('🚨 [BEFORE CALCULATION] Final rate check:', {
                'tasa_parameter': tasa,
                'plazo_parameter': plazo,
                'margin_would_be': 1 + (plazo * tasa),
                'user_role': window.CURRENT_USER?.role
            });
            
            const calculos = this.calcularValoresEquipo(costo, tasa, plazo, residual, tarifaSeguro, cantidad);
            
            this.mostrarCalculosEnVivo(calculos);
        } else {
            this.limpiarCalculosEnVivo();
        }
    }

    calcularValoresEquipo(costo, tasa, plazo, residual = 20, tarifaSeguro = 0.006, cantidad = 1, anticipoProporcional = 0, includeGPS = null, includePlacas = null) {
        const comision = window.asistente?.datosCotizacion?.comision || 0;
        const tarifaIva = 0.16; // 16% IVA

        // Cálculo del margen: tasa viene mensual como decimal (ej: 0.02 = 2% mensual)
        // Redondear a 2 decimales
        const margen = Math.round((1 + (plazo * tasa)) * 100) / 100;

        // Validar costo básico
        if (!costo || costo <= 0) {
            return {
                equipmentPayment: 0,
                insurance: 0,
                subtotal: 0,
                iva: 0,
                totalMonthlyPayment: 0,
                residualValue: 0,
                totalContract: 0
            };
        }

        // Obtener configuración del equipo y costos adicionales
        // Si includeGPS/includePlacas son null, usar la configuración del formulario (para vista preliminar)
        // Si tienen valores, usar esos valores (para equipos guardados)
        const equipmentConfig = (includeGPS !== null && includePlacas !== null)
            ? { incluirGPS: includeGPS, incluirPlacas: includePlacas }
            : this.obtenerConfiguracionEquipo();
        const costosAdicionales = this.obtenerCostosAdicionales();

        let costoBase = parseFloat(costo);
        let costoPlacasAgregado = 0;
        let costoGpsAgregado = 0;

        // Agregar GPS solo si está habilitado en el catálogo
        if (equipmentConfig.incluirGPS && costosAdicionales.GPS) {
            costoGpsAgregado = parseFloat(costosAdicionales.GPS);
            costoBase += costoGpsAgregado;
        }

        // Agregar Placas solo si está habilitado en el catálogo
        if (equipmentConfig.incluirPlacas && costosAdicionales.PLACAS) {
            costoPlacasAgregado = parseFloat(costosAdicionales.PLACAS);
            costoBase += costoPlacasAgregado;
        }

        // APLICAR ANTICIPO PROPORCIONAL AL COSTO BASE
        // El anticipo se distribuye proporcionalmente y se descuenta del costoBase ANTES de aplicar margen
        const anticipoPorUnidad = cantidad > 0 ? anticipoProporcional / cantidad : 0;
        const costoBaseConAnticipo = costoBase - anticipoPorUnidad;

        const costoEquipo = margen * costoBaseConAnticipo; // COSTO VENTA sobre costo con anticipo
        const seguro = costoBaseConAnticipo * tarifaSeguro; // Seguro sobre costo base con anticipo descontado
        
        // Cálculo del residual
        const montoResidual = costoEquipo * (residual / 100);
        
        // Pago mensual del equipo (sin comisión)
        const pagoMensualEquipo = (costoEquipo - montoResidual) / plazo;
        
        // Cálculo de comisión (comisión total sobre COSTO VENTA)
        const comisionTotal = costoEquipo * comision;
        
        // NUEVO DESGLOSE: Separar equipo, seguro y subtotal
        const equipoSolo = pagoMensualEquipo; // Solo el pago mensual del equipo
        const seguroSolo = seguro; // Seguro separado
        const subtotalEquipoMasSeguro = pagoMensualEquipo + seguro; // Subtotal = Equipo + Seguro
        
        // IVA aplicado al subtotal (Equipo + Seguro)
        const ivaDelSubtotal = subtotalEquipoMasSeguro * tarifaIva;
        
        // Pago mensual total (Subtotal + IVA)
        const pagoMensualTotal = subtotalEquipoMasSeguro + ivaDelSubtotal;
        
        // Cálculos del RESIDUAL
        const ivaResidual = montoResidual * tarifaIva;
        const pagoResidual1 = montoResidual + ivaResidual; // Residual + IVA
        const pagoResidual3 = ((montoResidual + ivaResidual) * 1.1) / 3; // Factor 1.1 según SP
        
        // Variables para mantener compatibilidad con el resto del código
        const subtotalCompleto = subtotalEquipoMasSeguro;
        const ivaCompleto = ivaDelSubtotal;
        const pagoMensualTotalConExtras = pagoMensualTotal;

        // Cálculos totales para todas las unidades
        const costoTotalEquipo = costoEquipo * cantidad;
        const seguroTotal = seguro * cantidad;
        const pagoMensualFinal = pagoMensualTotal * cantidad;
        const pagoMensualFinalConExtras = pagoMensualTotalConExtras * cantidad;
        const costoTotal = costoBase * cantidad; // Usar costoBase que ya incluye GPS/Placas

        // Porcentaje de utilidad usando la fórmula: 1 - (costoCompra / costoVenta)
        const utilidadEquipo = costoTotalEquipo > 0 ? parseFloat((1 - (costoTotal / costoTotalEquipo)).toFixed(2)) : 0;

        // Calcular pago mensual de GPS y Placas con interés del 10% anual compuesto
        // Fórmula: Pago = (Costo × (1 + 0.10)^(plazo/12)) / plazo
        const tasaAnualGPSPlacas = 0.10; // 10% anual
        const factorInteres = Math.pow(1 + tasaAnualGPSPlacas, plazo / 12);

        const pagoGPS = costoGpsAgregado > 0
            ? (costoGpsAgregado * factorInteres) / plazo
            : 0;

        const pagoPlacas = costoPlacasAgregado > 0
            ? (costoPlacasAgregado * factorInteres) / plazo
            : 0;

        // LOG CONSOLIDADO DE CÁLCULOS
        console.log('📊 CÁLCULOS FINALES:', {
            '🔢 ENTRADA': {
                costo: costo,
                cantidad: cantidad,
                plazo: plazo,
                tasa: (tasa * 100).toFixed(2) + '%',
                residual: residual + '%',
                anticipoProporcional: anticipoProporcional
            },
            '💰 COSTOS': {
                costoBase: costoBase.toFixed(2),
                GPS: costoGpsAgregado.toFixed(2),
                Placas: costoPlacasAgregado.toFixed(2),
                anticipoPorUnidad: anticipoPorUnidad.toFixed(2),
                costoBaseConAnticipo: costoBaseConAnticipo.toFixed(2)
            },
            '📈 CÁLCULO': {
                margen: margen.toFixed(4),
                costoEquipo: costoEquipo.toFixed(2),
                seguro: seguro.toFixed(2),
                residual: montoResidual.toFixed(2)
            },
            '💵 PAGO MENSUAL': {
                equipo: equipoSolo.toFixed(2),
                seguro: seguroSolo.toFixed(2),
                subtotal: subtotalCompleto.toFixed(2),
                IVA: ivaCompleto.toFixed(2),
                total: pagoMensualTotal.toFixed(2)
            },
            '📄 TOTAL CONTRATO (sin seguro)': (equipoSolo * plazo * cantidad).toFixed(2),
            '📊 UTILIDAD': (utilidadEquipo * 100).toFixed(2) + '%'
        });

        return {
            // Cálculos por unidad
            margin: this.redondearDecimales(margen, 4),
            saleCost: this.redondearDecimales(costoEquipo, 2),
            equipmentPayment: this.redondearDecimales(pagoMensualEquipo, 2),
            insurance: this.redondearDecimales(seguro, 2),
            residualAmount: this.redondearDecimales(montoResidual, 2),
            totalPayment: this.redondearDecimales(pagoMensualTotal, 2),
            totalPaymentWithExtras: this.redondearDecimales(pagoMensualTotalConExtras, 2),
            
            // NUEVO DESGLOSE MENSUAL - Separado por componentes
            monthlyEquipmentPayment: this.redondearHaciaAbajo(equipoSolo, 2), // Solo equipo (GPS/Placas incluidos)
            monthlyInsurance: this.redondearHaciaAbajo(seguroSolo, 2), // Seguro separado
            monthlySubtotal: this.redondearHaciaAbajo(subtotalCompleto, 2), // Subtotal (Equipo + Seguro)
            monthlyIVA: this.redondearHaciaAbajo(ivaCompleto, 2), // IVA sobre subtotal
            totalMonthlyPayment: this.redondearHaciaAbajo(pagoMensualTotalConExtras, 2), // Total mensual final
            totalMonthlyPaymentWithExtras: this.redondearHaciaAbajo(pagoMensualTotalConExtras, 2), // Total mensual (compatibilidad)
            
            // Configuración del equipo para mostrar condicionalmente GPS/Placas
            includeGPS: equipmentConfig.incluirGPS,
            includePlacas: equipmentConfig.incluirPlacas,
            costoGpsAgregado: costoGpsAgregado,
            costoPlacasAgregado: costoPlacasAgregado,
            pagoGPS: this.redondearHaciaAbajo(pagoGPS, 2), // Pago mensual GPS con interés
            pagoPlacas: this.redondearHaciaAbajo(pagoPlacas, 2), // Pago mensual Placas con interés
            
            // Comisión y residual  
            totalCommission: this.redondearDecimales(comisionTotal, 2),
            totalCommissionAmount: this.redondearDecimales(comisionTotal, 2),
            residualIVA: this.redondearDecimales(ivaResidual, 2),
            residual1Payment: this.redondearDecimales(pagoResidual1, 2),
            residual3Payments: this.redondearDecimales(pagoResidual3, 2),
            
            // Cálculos totales
            totalSaleCost: this.redondearDecimales(costoTotalEquipo, 2),
            totalEquipmentPayment: this.redondearDecimales(pagoMensualEquipo * cantidad, 2),
            totalInsurance: this.redondearDecimales(seguroTotal, 2),
            totalMonthlyPaymentFinal: this.redondearHaciaAbajo(pagoMensualFinal, 2),
            totalMonthlyPaymentWithExtras: this.redondearHaciaAbajo(pagoMensualFinalConExtras, 2),
            totalCost: this.redondearDecimales(costoTotal, 2),
            totalUtility: utilidadEquipo,
            
            // Información adicional
            quantity: cantidad,
            term: plazo,
            rate: tasa,
            residualPercentage: residual,
            insuranceRate: tarifaSeguro,
            
            // Porcentajes de utilidad
            utilityPercentage: utilidadEquipo,
            
            // Aliases para compatibilidad con wizard.js
            equipmentCost: this.redondearDecimales(costoEquipo, 2),
            totalEquipmentCost: this.redondearDecimales(costoTotalEquipo, 2),
            totalAgrupado: this.redondearHaciaAbajo(this.redondearHaciaAbajo(pagoMensualTotal, 2) * cantidad, 2),
            totalResidual: this.redondearHaciaAbajo(this.redondearHaciaAbajo(pagoResidual1, 2)  * cantidad, 2)        
        };
    }

    obtenerTarifaSeguro(tipoEquipo) {
        const select = document.getElementById('tipo-equipo');
        if (select && tipoEquipo) {
            const opcion = select.querySelector(`option[value="${tipoEquipo}"]`);
            if (opcion && opcion.dataset.insurance) {
                return parseFloat(opcion.dataset.insurance);
            }
        }
        return 0.006; // Tarifa de seguro por defecto
    }
    
    obtenerConfiguracionEquipo() {
        const tipoEquipo = document.getElementById('tipo-equipo')?.value;
        const opcion = document.querySelector(`#tipo-equipo option[value="${tipoEquipo}"]`);
        return {
            incluirPlacas: opcion ? (opcion.dataset.incluirPlacas === '1' || opcion.dataset.incluirPlacas === 'true') : true,
            incluirGPS: opcion ? (opcion.dataset.incluirGps === '1' || opcion.dataset.incluirGps === 'true') : true
        };
    }
    
    obtenerCostosAdicionales() {
        // Obtener costos desde los catálogos cargados
        if (window.CATALOGOS_DATA && window.CATALOGOS_DATA.additionalCosts) {
            const costos = {};
            window.CATALOGOS_DATA.additionalCosts.forEach(item => {
                costos[item.codigo] = item.costo;
            });
            console.log('Costos adicionales desde catálogos:', costos);
            return costos;
        }
        // Fallback a valores por defecto
        console.log('Usando costos adicionales por defecto (catálogos no disponibles)');
        return { PLACAS: 4200, GPS: 3300 };
    }

    generarDescripcionExtras(includeGPS, includePlacas) {
        const incluye = [];
        if (includePlacas) incluye.push('Placas');
        if (includeGPS) incluye.push('GPS');
        
        if (incluye.length === 0) return '';
        else if (incluye.length === 1) return `(${incluye[0]} incluido)`;
        else return `(${incluye.join(' y ')} incluidos)`;
    }

    mostrarCalculosEnVivo(calculos) {
        // Debug: verificar el objeto calculos
        console.log('Objeto calculos en mostrarCalculosEnVivo:', calculos);
        
        // Crear o actualizar display de cálculo en vivo
        let contenedorDisplay = document.getElementById('calculos-en-vivo');
        
        if (!contenedorDisplay) {
            contenedorDisplay = this.crearContenedorCalculosEnVivo();
        }

        // Verificar permisos para mostrar información sensible
        const puedeVerSensible = window.CONFIG_PERMISOS?.puedeVerInformacionSensible || false;

        let html = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-4">
                <h4 class="text-sm font-medium text-blue-900 mb-2">Cálculo preliminar</h4>
                <div class="space-y-1 text-xs">`;
        
        // Solo mostrar costo de venta si es vendedor
        if (puedeVerSensible) {
            html += `
                    <div class="flex justify-between py-1">
                        <span class="text-blue-700">Costo venta:</span>
                        <span class="font-medium">$${(calculos.totalSaleCost || calculos.saleCost * calculos.quantity || 0).toLocaleString()}</span>
                    </div>`;
        }
        
        // Agregar desglose mensual con GPS/Placas separados
        html += `
                    <div class="text-blue-800 font-medium mb-1">Desglose mensual:</div>`;

        // Calcular costos mensuales de GPS y Placas
        const term = calculos.term || 12;
        const costoGpsMensual = calculos.includeGPS && calculos.costoGpsAgregado ? calculos.costoGpsAgregado / term : 0;
        const costoPlacasMensual = calculos.includePlacas && calculos.costoPlacasAgregado ? calculos.costoPlacasAgregado / term : 0;
        const equipoSinExtras = (calculos.monthlyEquipmentPayment || 0) - costoGpsMensual - costoPlacasMensual;

        html += `
                    <div class="flex justify-between py-1 ml-2">
                        <span class="text-blue-600">Equipo:</span>
                        <span class="font-medium">$${this.redondearHaciaAbajo(equipoSinExtras, 2).toLocaleString()}</span>
                    </div>`;

        // Mostrar GPS si está incluido
        if (calculos.includeGPS && costoGpsMensual > 0) {
            html += `
                    <div class="flex justify-between py-1 ml-2">
                        <span class="text-blue-600">GPS:</span>
                        <span class="font-medium">$${this.redondearHaciaAbajo(costoGpsMensual, 2).toLocaleString()}</span>
                    </div>`;
        }

        // Mostrar Placas si están incluidas
        if (calculos.includePlacas && costoPlacasMensual > 0) {
            html += `
                    <div class="flex justify-between py-1 ml-2">
                        <span class="text-blue-600">Placas:</span>
                        <span class="font-medium">$${this.redondearHaciaAbajo(costoPlacasMensual, 2).toLocaleString()}</span>
                    </div>`;
        }

        html += `
                    <div class="flex justify-between py-1 ml-2">
                        <span class="text-blue-600">Seguro:</span>
                        <span class="font-medium">$${(calculos.monthlyInsurance || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between py-1 ml-2 border-t border-blue-200">
                        <span class="text-blue-700">Subtotal:</span>
                        <span class="font-medium">$${(calculos.monthlySubtotal || calculos.totalPayment || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between py-1 ml-2">
                        <span class="text-blue-600">IVA (16%):</span>
                        <span class="font-medium">$${(calculos.monthlyIVA || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between py-1 ml-2 border-t border-blue-200 font-semibold">
                        <span class="text-blue-700">Total mensual:</span>
                        <span class="font-medium text-blue-900">$${(calculos.totalMonthlyPayment || 0).toLocaleString()}</span>
                    </div>
                    
                    <div class="border-t border-blue-300 my-2 pt-2">
                        <div class="text-blue-800 font-medium mb-1">Opciones de Residual (sin IVA):</div>
                        <div class="flex justify-between py-1 ml-2">
                            <span class="text-blue-600">• 1 pago final:</span>
                            <span class="font-medium text-orange-600">$${((calculos.residualAmount || 0) * (calculos.quantity || 1)).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between py-1 ml-2">
                            <span class="text-blue-600">• 3 pagos de:</span>
                            <span class="font-medium text-orange-600">$${(((calculos.residualAmount || 0) * 1.1 / 3) * (calculos.quantity || 1)).toLocaleString()}</span>
                        </div>
                        
                    </div>`;
        
        // Solo mostrar utilidad si es vendedor
        if (puedeVerSensible) {
            html += `
                    <div class="border-t border-blue-300 my-2 pt-2">
                        <div class="flex justify-between py-1">
                            <span class="text-blue-700">Utilidad:</span>
                            <span class="font-medium text-green-600">${(calculos.totalUtility * 100).toFixed(2)}%</span>
                        </div>
                    </div>`;
        }
        
        html += `
                </div>`;

        // Solo mostrar total del contrato si es vendedor/admin
        if (puedeVerSensible) {
            // Obtener anticipo del wizard si existe
            const anticipo = window.asistente?.datosCotizacion?.anticipo || 0;

            // Calcular total del contrato SIN seguro, SIN IVA (NO restar anticipo)
            const pagoEquipoMensual = calculos.monthlyEquipmentPayment || 0;
            const cantidadEquipos = calculos.quantity || 1;
            const plazo = calculos.term || 1;
            const totalContrato = pagoEquipoMensual * cantidadEquipos * plazo;

            html += `
                <div class="mt-2 pt-2 border-t border-blue-200">`;

            // Mostrar anticipo si existe (solo informativo, NO se resta del total)
            if (anticipo > 0) {
                html += `
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-blue-700">Anticipo:</span>
                        <span class="text-sm font-medium text-green-600">$${anticipo.toLocaleString()}</span>
                    </div>`;
            }

            html += `
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-blue-900">Total del contrato (sin seguro, sin IVA):</span>
                        <span class="text-lg font-bold text-blue-900">$${totalContrato.toLocaleString()}</span>
                    </div>
                </div>`;
        }

        html += `
            </div>
        `;

        contenedorDisplay.innerHTML = html;
    }

    crearContenedorCalculosEnVivo() {
        const contenedor = document.createElement('div');
        contenedor.id = 'calculos-en-vivo';
        
        const formularioEquipo = document.getElementById('formulario-equipo');
        if (formularioEquipo) {
            formularioEquipo.appendChild(contenedor);
        }
        
        return contenedor;
    }

    limpiarCalculosEnVivo() {
        const contenedorDisplay = document.getElementById('calculos-en-vivo');
        if (contenedorDisplay) {
            contenedorDisplay.innerHTML = '';
        }
    }

    // Funciones de utilidad financiera
    calcularPagoMensual(principal, tasa, plazo) {
        if (tasa === 0) return principal / plazo;
        
        const tasaMensual = tasa / 100 / 12;
        const numeroPagos = plazo;
        
        return principal * (tasaMensual * Math.pow(1 + tasaMensual, numeroPagos)) / 
               (Math.pow(1 + tasaMensual, numeroPagos) - 1);
    }

    calcularValorPresente(valorFuturo, tasa, plazo) {
        const tasaMensual = tasa / 100 / 12;
        return valorFuturo / Math.pow(1 + tasaMensual, plazo);
    }

    calcularValorFuturo(valorPresente, tasa, plazo) {
        const tasaMensual = tasa / 100 / 12;
        return valorPresente * Math.pow(1 + tasaMensual, plazo);
    }

    calcularInteresTotal(principal, pagoMensual, plazo) {
        return (pagoMensual * plazo) - principal;
    }

    calcularTasaEfectiva(tasaNominal, periodosCapitalizacion) {
        return Math.pow(1 + (tasaNominal / periodosCapitalizacion), periodosCapitalizacion) - 1;
    }

    // Funciones de validación
    validarEntradasFinancieras(costo, tasa, plazo, cantidad = 1) {
        const errores = [];

        if (!costo || costo <= 0) {
            errores.push('El costo debe ser mayor a cero');
        }

        if (costo > 99999999) {
            errores.push('El costo excede el límite máximo');
        }

        if (!tasa || tasa <= 0) {
            errores.push('La tasa debe ser mayor a cero');
        }

        if (tasa > 50) {
            errores.push('La tasa parece muy alta (>50%)');
        }

        if (!plazo || plazo < 12) {
            errores.push('El plazo mínimo es 12 meses');
        }

        if (plazo > 84) {
            errores.push('El plazo máximo es 84 meses');
        }

        if (!cantidad || cantidad < 1) {
            errores.push('La cantidad debe ser al menos 1');
        }

        if (cantidad > 999) {
            errores.push('La cantidad máxima es 999');
        }

        return {
            esValido: errores.length === 0,
            errores: errores
        };
    }

    // Utilidades de formato
    formatearMoneda(monto, moneda = 'MXN', incluirSimbolo = true) {
        const formateado = new Intl.NumberFormat('es-MX', {
            style: incluirSimbolo ? 'currency' : 'decimal',
            currency: moneda,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(monto);

        return formateado;
    }

    formatearPorcentaje(valor, decimales = 2) {
        return `${valor.toFixed(decimales)}%`;
    }

    formatearNumero(valor, decimales = 2) {
        return valor.toLocaleString('es-MX', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    }

    // Función de utilidad para cálculos decimales precisos
    redondearDecimales(valor, decimales) {
        const factor = Math.pow(10, decimales);
        return Math.round(valor * factor) / factor;
    }

    // Función para redondear hacia abajo (para pagos mensuales)
    redondearHaciaAbajo(valor, decimales) {
        const factor = Math.pow(10, decimales);
        return Math.floor(valor * factor) / factor;
    }

    // Comparar diferentes escenarios de financiamiento
    compararEscenarios(escenarios) {
        return escenarios.map(escenario => {
            const calculos = this.calcularValoresEquipo(
                escenario.costo,
                escenario.tasa,
                escenario.plazo,
                escenario.residual || this.residualPorDefecto,
                escenario.tarifaSeguro || 0.006,
                escenario.cantidad || 1
            );

            return {
                ...escenario,
                calculos: calculos,
                costoTotal: calculos.totalMonthlyPayment * escenario.plazo,
                costoPorMes: calculos.totalMonthlyPayment
            };
        }).sort((a, b) => a.costoTotal - b.costoTotal);
    }

    // Generar tabla de amortización
    generarTablaAmortizacion(principal, tasa, plazo) {
        const tasaMensual = tasa / 100 / 12;
        const pagoMensual = this.calcularPagoMensual(principal, tasa, plazo);
        const tabla = [];
        let saldo = principal;

        for (let mes = 1; mes <= plazo; mes++) {
            const pagoInteres = saldo * tasaMensual;
            const pagoPrincipal = pagoMensual - pagoInteres;
            saldo -= pagoPrincipal;

            tabla.push({
                mes: mes,
                pago: this.redondearDecimales(pagoMensual, 2),
                principal: this.redondearDecimales(pagoPrincipal, 2),
                interes: this.redondearDecimales(pagoInteres, 2),
                saldo: this.redondearDecimales(Math.max(0, saldo), 2)
            });
        }

        return tabla;
    }

    // Exportar cálculos para reportes
    exportarCalculos(datosCotizacion) {
        const resumen = {
            cotizacion: {
                nombreCliente: datosCotizacion.nombreCliente,
                tipoCliente: datosCotizacion.tipoCliente,
                tasa: datosCotizacion.tasa,
                creadoEn: new Date().toISOString()
            },
            equipos: datosCotizacion.equipos.map(item => ({
                ...item,
                calculos: item.calculations
            })),
            totales: datosCotizacion.totales,
            resumen: {
                totalEquipos: datosCotizacion.equipos.length,
                plazoPromedio: datosCotizacion.equipos.reduce((suma, item) => suma + item.term, 0) / datosCotizacion.equipos.length,
                costoTotal: datosCotizacion.equipos.reduce((suma, item) => suma + (item.cost * item.quantity), 0),
                contratoTotal: datosCotizacion.totales.contrato,
                utilidadTotal: datosCotizacion.totales.utilidad,
                margenGanancia: (datosCotizacion.totales.utilidad / datosCotizacion.totales.contrato * 100).toFixed(2) + '%'
            }
        };

        return resumen;
    }
}

// Inicializar controlador de cálculos inmediatamente
// console.log('🚀 Inicializando ControladorCalculos...');

function inicializarCalculos() {
    if (!window.controladorCalculos) {
        // console.log('📝 Creando nueva instancia de ControladorCalculos...');
        window.controladorCalculos = new ControladorCalculos();
        // console.log('✅ window.controladorCalculos creado:', window.controladorCalculos);
    }
}

if (document.readyState === 'loading') {
    // console.log('⏳ DOM aún cargando para cálculos, esperando...');
    document.addEventListener('DOMContentLoaded', inicializarCalculos);
} else {
    // console.log('✅ DOM listo para cálculos, inicializando...');
    inicializarCalculos();
}