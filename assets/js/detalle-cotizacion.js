class DetalleCotizacion {
    constructor() {
        this.cotizacionId = window.COTIZACION_ID;
        this.cotizacion = null;
        this.equipos = [];
        this.modoEdicion = false;
        this.cambiosPendientes = false;
        this.catalogos = null; // Para almacenar los catálogos
        
        this.inicializar();
    }

    async inicializar() {
        await this.cargarDetalle();
        await this.cargarCatalogos(); // Cargar catálogos para el modo edición
        this.vincularEventos();
        this.importarCalculadora();
    }

    vincularEventos() {
        // Botón imprimir
        // document.getElementById('btn-imprimir').addEventListener('click', () => this.imprimir());
        
        // Botones de admin
        // document.getElementById('btn-duplicar').addEventListener('click', () => this.duplicar());
        document.getElementById('btn-eliminar').addEventListener('click', () => this.eliminar());
        
        // Botones de edición (solo para admin/vendor)
        if (this.puedeEditar()) {
            const btnEditar = document.getElementById('btn-editar');
            const btnGuardar = document.getElementById('btn-guardar');
            const btnCancelar = document.getElementById('btn-cancelar');
            
            if (btnEditar) btnEditar.addEventListener('click', () => this.activarModoEdicion());
            if (btnGuardar) btnGuardar.addEventListener('click', () => this.guardarCambios());
            if (btnCancelar) btnCancelar.addEventListener('click', () => this.cancelarEdicion());
        }
    }

    async cargarDetalle() {
        try {
            const respuesta = await fetch(`api/obtener_detalle_cotizacion.php?id=${this.cotizacionId}`);
            const datos = await respuesta.json();

            if (datos.status === 'success') {
                this.cotizacion = datos.data.cotizacion;
                this.equipos = datos.data.equipos;
                
                // Store original equipment data for tracking deletions
                this.equiposOriginales = JSON.parse(JSON.stringify(datos.data.equipos));
                
                // 🔍 DEBUG: Ver qué datos llegan de la API
                console.log('🔍 DEBUG - Datos completos de la API:', datos.data);
                console.log('🔍 DEBUG - Cotización:', this.cotizacion);
                console.log('🔍 DEBUG - Equipos:', this.equipos);
                console.log('🔍 DEBUG - Primer equipo ejemplo:', this.equipos[0]);
                
                this.renderizarDetalle();
            } else {
                this.mostrarError(datos.message);
            }
        } catch (error) {
            console.error('Error cargando detalle:', error);
            this.mostrarError('Error al cargar el detalle de la cotización');
        } finally {
            this.ocultarLoading();
        }
    }

    async cargarCatalogos() {
        try {
            const respuesta = await fetch('api/obtener_catalogos_edicion.php');
            const datos = await respuesta.json();

            if (datos.status === 'success') {
                this.catalogos = datos.data;
                console.log('Catálogos cargados:', this.catalogos);
            } else {
                console.error('Error cargando catálogos:', datos.message);
            }
        } catch (error) {
            console.error('Error de conexión al cargar catálogos:', error);
        }
    }

    renderizarDetalle() {
        // Header info
        document.getElementById('cotizacion-numero').textContent = `#${this.cotizacion.id}`;
        document.getElementById('numero-cotizacion-header').textContent = this.cotizacion.id;
        
        // document.getElementById('cliente-info').innerHTML = `
        //     <div>${this.cotizacion.cliente}</div>
        //     <div class="text-xs text-gray-500">${this.cotizacion.tipoCliente}</div>
        // `;
        // document.getElementById('vendedor-info').textContent = this.cotizacion.vendedor;
        document.getElementById('fecha-info').textContent = this.cotizacion.fechaFormateada;
        
        // Estado badge
        const estadoBadge = document.getElementById('estado-badge');
        estadoBadge.innerHTML = this.renderEstado(this.cotizacion.estado);

        // Equipos
        this.renderizarEquipos();

        // Información del contrato
        document.getElementById('moneda-contrato').textContent = this.cotizacion.moneda;
        document.getElementById('plazo-contrato').textContent = `${this.cotizacion.plazo} meses`;
        document.getElementById('porcentaje-residual').textContent = `${this.cotizacion.porcentajeResidual}%`;

        // Información sensible (oculta para clientes)
        if (window.CURRENT_USER.role === 'client') {
            document.getElementById('info-sensible').classList.add('hidden');
            document.getElementById('totales-financieros').classList.add('hidden');
        } else {
            document.getElementById('tasa-interes').textContent = `${(this.cotizacion.tasa * 100).toFixed(2)}%`;   
            document.getElementById('comision-valor').textContent = `${this.cotizacion.comision * 100}%`;
            
            // Totales financieros
            document.getElementById('total-contrato').textContent = this.formatearMoneda(this.cotizacion.totalContrato);
            document.getElementById('utilidad-total').textContent = `${(this.cotizacion.utilidad * 100).toFixed(1)}%`;
            
            // Totales de residuales
            // Debug residuales
            console.log('🔍 Debug residuales:', {
                totalResidual1Pago: this.cotizacion.totalResidual1Pago,
                totalResidual3Pagos: this.cotizacion.totalResidual3Pagos,
                cotizacionCompleta: this.cotizacion
            });
            
            document.getElementById('total-residual-1pago').textContent = this.formatearMoneda(this.cotizacion.totalResidual1Pago || 0);
            document.getElementById('total-residual-3pagos').textContent = this.formatearMoneda(this.cotizacion.totalResidual3Pagos || 0);
        }

        // Calcular totales básicos desde this.equipos
        this.calcularYMostrarTotalesBasicos();

        // Mostrar acciones de admin
        if (window.CURRENT_USER.role === 'admin') {
            document.getElementById('admin-actions').classList.remove('hidden');
        }

        // Mostrar botones de edición para admin/vendor
        if (this.puedeEditar()) {
            document.getElementById('edit-actions').classList.remove('hidden');
        }

        // Mostrar contenido principal
        document.getElementById('main-content').classList.remove('hidden');
    }

    renderizarEquipos() {
        const container = document.getElementById('lista-equipos');
        container.innerHTML = '';

        if (this.equipos.length === 0) {
            container.innerHTML = `
                <div class="text-center py-6 text-gray-500">
                    No se encontraron equipos en esta cotización
                </div>
            `;
            return;
        }

        // console.log(this.equipos);

        // Agregar sección de resumen de equipos
        this.renderizarResumenEquipos(container);

        this.equipos.forEach((equipo, index) => {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg p-4 shadow-sm border';
            card.id = `equipo-card-${index}`; // Add ID for scroll targeting
            
            // Calcular los valores para el desglose mensual
            // NUEVO SISTEMA: Si tenemos los campos nuevos, los usamos
            let pagoMensualEquipo, seguroSeparado, subtotalMensual, ivaMensual, totalMensual;
            let calculations = {};
            
            if (equipo.monthlyEquipmentPayment && equipo.monthlyInsurance) {
                // NUEVO SISTEMA: Usar campos separados
                pagoMensualEquipo = equipo.monthlyEquipmentPayment || 0;
                seguroSeparado = equipo.monthlyInsurance || 0;
                subtotalMensual = equipo.monthlySubtotal || (pagoMensualEquipo + seguroSeparado);
                ivaMensual = equipo.monthlyIVA || (subtotalMensual * 0.16);
                totalMensual = equipo.totalMonthlyPayment || (subtotalMensual + ivaMensual);
                
                // Configuración GPS/Placas si está disponible
                calculations = {
                    includeGPS: equipo.includeGPS || false,
                    includePlacas: equipo.includePlacas || false
                };
            } else {
                // SISTEMA ANTERIOR: Compatibilidad con cotizaciones guardadas anteriormente
                pagoMensualEquipo = (equipo.pagoEquipo || 0);
                seguroSeparado = equipo.seguro || 0;
                const placasGpsMensual = (equipo.placasMonthlyPayment || 0) + (equipo.gpsMonthlyPayment || 0);
                subtotalMensual = pagoMensualEquipo + seguroSeparado + placasGpsMensual;
                ivaMensual = subtotalMensual * 0.16;
                totalMensual = subtotalMensual + ivaMensual;
                
                // En sistema anterior, GPS y Placas siempre están incluidos si hay costos > 0
                calculations = {
                    includeGPS: equipo.includeGPS || (equipo.costoGpsAgregado || 0) > 0,
                    includePlacas: equipo.includePlacas || (equipo.costoPlacasAgregado || 0) > 0
                };
            }
            
            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">${equipo.cantidad}x ${equipo.nombre}</h3>
                        <p class="text-sm text-gray-500">${equipo.marca}${equipo.modelo ? ` - ${equipo.modelo}` : ''}</p>
                        <p class="text-sm text-gray-500">Costo: ${this.formatearMoneda(equipo.precio)}</p>
                    </div>
                    <div class="text-right ml-3">
                        <div class="text-xs text-gray-500">Pago Mensual</div>
                        <div class="text-lg font-semibold text-accent">${this.formatearMoneda(totalMensual)}</div>
                    </div>
                </div>
                
                <!-- Desglose Mensual -->
                <div class="bg-gray-50 rounded-lg p-3 mb-3">
                    <div class="text-sm font-medium text-gray-700 mb-2">Desglose mensual por equipo:</div>
                    <div class="space-y-1 text-sm">
                        ${this.generarDesgloseMensualDetalle(equipo, pagoMensualEquipo, calculations)}
                        <div class="flex justify-between">
                            <span class="text-gray-600">Seguro:</span>
                            <span class="text-gray-900">${this.formatearMoneda(seguroSeparado)}</span>
                        </div>
                        <div class="flex justify-between border-t pt-1">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">${this.formatearMoneda(subtotalMensual)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">IVA (16%):</span>
                            <span class="text-gray-900">${this.formatearMoneda(ivaMensual)}</span>
                        </div>
                        <div class="flex justify-between border-t pt-1 font-semibold">
                            <span>Total mensual:</span>
                            <span class="text-accent">${this.formatearMoneda(totalMensual)}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Valores Residuales -->
                <div class="bg-blue-50 rounded-lg p-3">
                    <div class="text-sm font-medium text-blue-700 mb-2">Valores residuales por equipo:</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-blue-600">Valor residual (${this.cotizacion.porcentajeResidual}%):</span>
                            <span class="text-blue-900">${this.formatearMoneda(equipo.valorResidual || 0)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-blue-600">IVA residual:</span>
                            <span class="text-blue-900">${this.formatearMoneda(equipo.ivaResidual || 0)}</span>
                        </div>
                        <div class="flex justify-between border-t border-blue-200 pt-1">
                            <span class="text-blue-600">1 pago:</span>
                            <span class="font-medium text-blue-900">${this.formatearMoneda(equipo.residual1Pago || 0)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-blue-600">3 pagos:</span>
                            <span class="font-medium text-blue-900">${this.formatearMoneda((equipo.residual3Pagos || 0) * 3)}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-blue-500">Por pago (3 pagos):</span>
                            <span class="text-blue-800">${this.formatearMoneda(equipo.residual3Pagos || 0)}</span>
                        </div>
                    </div>
                </div>
                
            `;
            
            container.appendChild(card);
        });

        
    }

    renderizarResumenEquipos(container) {
        // Generar resumen de equipos agrupados por tipo
        const resumenEquipos = this.generarResumenEquipos();
        
        // Calcular total mensual
        let totalMensualGeneral = 0;
        this.equipos.forEach(equipo => {
            let totalMensual = 0;
            if (equipo.monthlyEquipmentPayment && equipo.monthlyInsurance) {
                // Nuevo sistema
                const subtotal = (equipo.monthlyEquipmentPayment || 0) + (equipo.monthlyInsurance || 0);
                totalMensual = (subtotal + (subtotal * 0.16)) * equipo.cantidad;
            } else {
                // Sistema anterior
                const pagoMensualEquipo = equipo.pagoEquipo || 0;
                const seguro = equipo.seguro || 0;
                const placasGps = (equipo.placasMonthlyPayment || 0) + (equipo.gpsMonthlyPayment || 0);
                const subtotal = pagoMensualEquipo + seguro + placasGps;
                totalMensual = (subtotal + (subtotal * 0.16)) * equipo.cantidad;
            }
            totalMensualGeneral += totalMensual;
        });

        // Crear sección de resumen
        const resumenSection = document.createElement('div');
        resumenSection.className = 'mt-6 pt-6 border-t border-gray-200';
        
        resumenSection.innerHTML = `
            <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900 flex items-center mb-3">
                    <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Resumen de Equipos (${this.equipos.length} ${this.equipos.length === 1 ? 'equipo' : 'equipos'})
                </h3>
                
                <!-- Grid de tarjetas de resumen -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                    ${this.generarTarjetasResumen(resumenEquipos)}
                </div>
                
                <!-- Total mensual general -->
                <div class="bg-gradient-to-r from-blue-50 to-green-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">Total mensual general:</span>
                        <span class="text-xl font-bold text-green-600">${this.formatearMoneda(totalMensualGeneral)}</span>
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(resumenSection);
    }

    generarResumenEquipos() {
        const resumen = {};
        
        this.equipos.forEach((equipo, index) => {
            const tipoEquipo = equipo.nombre;
            const marca = equipo.marca || 'Sin marca';
            const clave = `${tipoEquipo}|${marca}`;
            
            if (!resumen[clave]) {
                resumen[clave] = {
                    tipoEquipo,
                    marca,
                    cantidad: 0,
                    indices: [] // Para la funcionalidad interactiva
                };
            }
            
            resumen[clave].cantidad += parseInt(equipo.cantidad);
            resumen[clave].indices.push(index);
        });
        
        return Object.values(resumen);
    }

    generarTarjetasResumen(resumenEquipos) {
        return resumenEquipos.map(item => {
            const indicesStr = item.indices.join(',');
            return `
                <div class="bg-white border border-gray-200 rounded-lg p-3 cursor-pointer hover:bg-gray-50 hover:border-blue-300 transition-all duration-200 hover:shadow-sm"
                     onclick="window.detalleCotizacion.scrollToEquipos([${indicesStr}])">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600 mb-1">${item.cantidad}x</div>
                        <div class="text-sm font-medium text-gray-900 leading-tight">${item.tipoEquipo}</div>
                        <div class="text-xs text-gray-500 mt-1">${item.marca}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    scrollToEquipos(indices) {
        // Hacer scroll al primer equipo del tipo seleccionado
        const primerIndice = indices[0];
        const equipoElement = document.getElementById(`equipo-card-${primerIndice}`);
        
        if (equipoElement) {
            equipoElement.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            
            // Highlight temporal
            indices.forEach(index => {
                const element = document.getElementById(`equipo-card-${index}`);
                if (element) {
                    element.classList.add('ring-2', 'ring-blue-300', 'ring-opacity-75');
                    setTimeout(() => {
                        element.classList.remove('ring-2', 'ring-blue-300', 'ring-opacity-75');
                    }, 2000);
                }
            });
        }
    }

    renderEstado(estado) {
        const colores = {
            'borrador': 'bg-yellow-100 text-yellow-800',
            'completada': 'bg-green-100 text-green-800', 
            'impresa': 'bg-blue-100 text-blue-800'
        };
        
        const color = colores[estado] || 'bg-gray-100 text-gray-800';
        
        return `
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color}">
                ${estado.charAt(0).toUpperCase() + estado.slice(1)}
            </span>
        `;
    }

    formatearMoneda(valor) {
        return '$' + Number(valor).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    ocultarLoading() {
        document.getElementById('loading-state').classList.add('hidden');
    }

    mostrarError(mensaje) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-message').textContent = mensaje;
        document.getElementById('error-state').classList.remove('hidden');
    }

    imprimir() {
        // Ocultar elementos no imprimibles temporalmente
        const elementos = document.querySelectorAll('.no-print');
        elementos.forEach(el => el.style.display = 'none');
        
        // Configurar para impresión
        document.title = `Cotización ${this.cotizacion.id} - WISER`;
        
        // Imprimir
        window.print();
        
        // Restaurar elementos
        elementos.forEach(el => el.style.display = '');
        document.title = 'Detalle de Cotización - WISER Cotizador';
    }

    duplicar() {
        if (confirm('¿Estás seguro de que quieres duplicar esta cotización? Se creará una nueva cotización con los mismos datos.')) {
            this.duplicarCotizacion();
        }
    }

    async duplicarCotizacion() {
        try {
            const respuesta = await fetch(`api/duplicar_cotizacion.php?id=${this.cotizacionId}`, {
                method: 'POST'
            });
            
            const datos = await respuesta.json();
            
            if (datos.status === 'success') {
                alert('Cotización duplicada exitosamente');
                window.location.href = `detalle-cotizacion.php?id=${datos.data.nueva_cotizacion_id}`;
            } else {
                alert('Error: ' + datos.message);
            }
        } catch (error) {
            console.error('Error duplicando cotización:', error);
            alert('Error al duplicar la cotización');
        }
    }

    eliminar() {
        if (confirm('¿Estás seguro de que quieres eliminar esta cotización? Esta acción no se puede deshacer.')) {
            this.eliminarCotizacion();
        }
    }

    async eliminarCotizacion() {
        try {
            const respuesta = await fetch(`api/eliminar_cotizacion.php?id=${this.cotizacionId}`, {
                method: 'DELETE'
            });
            
            const datos = await respuesta.json();
            
            if (datos.status === 'success') {
                alert('Cotización eliminada exitosamente');
                window.location.href = 'cotizaciones.php';
            } else {
                alert('Error: ' + datos.message);
            }
        } catch (error) {
            console.error('Error eliminando cotización:', error);
            alert('Error al eliminar la cotización');
        }
    }

    // Métodos para la calculadora y edición
    importarCalculadora() {
        // Importar la lógica de cálculos del wizard
        this.calculadora = {
            calcularValoresEquipo: (costo, tasa, plazo, residual = 20, tarifaSeguro = null, cantidad = 1, equipoConfig = null) => {
                const comision = this.cotizacion.comision || 0;
                const tarifaIva = 0.16; // 16% IVA

                // CORRECCIÓN CRÍTICA: Seguir el patrón del wizard
                // 1. Obtener tarifa de seguro específica del equipo
                let tarifaSeguroFinal = 0.006; // Valor por defecto
                if (equipoConfig && equipoConfig.equipmentName && this.catalogos && this.catalogos.equipment) {
                    const equipoInfo = this.catalogos.equipment.find(eq => eq.nombre === equipoConfig.equipmentName);
                    if (equipoInfo && equipoInfo.tarifaSeguro) {
                        tarifaSeguroFinal = parseFloat(equipoInfo.tarifaSeguro);
                    }
                }
                // Si se proporciona tarifaSeguro explícitamente, usar ese valor
                if (tarifaSeguro !== null) {
                    tarifaSeguroFinal = tarifaSeguro;
                }
                
                // 2. Crear COSTO BASE que incluye GPS/Placas si están habilitados
                let costoBase = costo;
                let costosPlacas = 0;
                let costosGPS = 0;
                
                // Obtener costos adicionales del catálogo
                const costosAdicionales = this.obtenerCostosAdicionales();
                
                // Solo agregar GPS/Placas AL COSTO BASE si están incluidos en la configuración del equipo
                if (equipoConfig && equipoConfig.includePlacas) {
                    costosPlacas = costosAdicionales.PLACAS || 4200;
                    costoBase += costosPlacas;
                }
                
                if (equipoConfig && equipoConfig.includeGPS) {
                    costosGPS = costosAdicionales.GPS || 3300;
                    costoBase += costosGPS;
                }
                
                console.log('🔧 [WIZARD REPLICATION] Cálculo de costo base:', {
                    'costo_original': costo,
                    'costosPlacas': costosPlacas,
                    'costosGPS': costosGPS,
                    'costo_base_final': costoBase,
                    'incluye_placas': equipoConfig?.includePlacas || false,
                    'incluye_gps': equipoConfig?.includeGPS || false
                });
                
                // 3. Calcular TODO basado en el costo base (como hace el wizard)
                const margen = 1 + (plazo * tasa);
                const costoEquipo = margen * costoBase; // COSTO VENTA sobre costo base
                const seguro = costoBase * tarifaSeguroFinal; // Seguro sobre costo base usando tarifa específica del equipo
                
                // Cálculo del residual
                const montoResidual = costoEquipo * (residual / 100);
                
                // Pago mensual del equipo (sin comisión)
                const pagoMensualEquipo = (costoEquipo - montoResidual) / plazo;
                
                // Cálculo de comisión (comisión total sobre COSTO VENTA)
                const comisionTotal = costoEquipo * comision;
                
                // 3. SUBTOTAL = Equipo + Seguro (GPS/Placas ya están incluidos en el costo base, NO se suman aquí)
                const subtotalMensual = pagoMensualEquipo + seguro;
                
                // IVA aplicado al subtotal
                const ivaMensual = subtotalMensual * tarifaIva;
                
                // Total mensual (Subtotal + IVA) - GPS/Placas ya incluidos
                const pagoMensualTotal = subtotalMensual + ivaMensual;
                
                console.log('🔧 [WIZARD REPLICATION] Cálculo final:', {
                    'margen': margen,
                    'costo_equipo': costoEquipo,
                    'seguro': seguro,
                    'pago_mensual_equipo': pagoMensualEquipo,
                    'subtotal_mensual': subtotalMensual,
                    'iva_mensual': ivaMensual,
                    'total_mensual': pagoMensualTotal
                });
                
                // Variables para compatibilidad (no hay cálculo adicional de placas/GPS)
                const pagoMensualPlacas = 0; // Ya incluido en costo base
                const pagoMensualGPS = 0; // Ya incluido en costo base
                const pagoMensualPlacasGPS = 0;
                
                // Los valores "completos" son iguales a los básicos porque GPS/Placas ya están incluidos
                const subtotalCompleto = Math.round(subtotalMensual * 100) / 100;
                const ivaCompleto = Math.round(ivaMensual * 100) / 100;
                const pagoMensualTotalConExtras = Math.round(pagoMensualTotal * 100) / 100;
                
                // Cálculos del RESIDUAL
                const ivaResidual = montoResidual * tarifaIva;
                const pagoResidual1 = montoResidual + ivaResidual; // Residual + IVA
                const pagoResidual3 = ((montoResidual + ivaResidual) * 1.1) / 3; // Factor 1.1 en 3 pagos

                // Cálculos totales para todas las unidades
                const costoTotalEquipo = costoEquipo * cantidad;
                const seguroTotal = seguro * cantidad;
                const pagoMensualFinal = pagoMensualTotal * cantidad;
                const pagoMensualFinalConExtras = pagoMensualTotalConExtras * cantidad;
                const pagoMensualPlacasTotal = pagoMensualPlacas * cantidad;
                const pagoMensualGPSTotal = pagoMensualGPS * cantidad;
                const pagoMensualPlacasGPSTotal = pagoMensualPlacasGPS * cantidad;
                const costoTotal = costo * cantidad;
                
                // Porcentaje de utilidad usando la fórmula: 1 - (costoCompra / costoVenta)
                const utilidadEquipo = costoTotalEquipo > 0 ? parseFloat((1 - (costoTotal / costoTotalEquipo)).toFixed(2)) : 0;

                return {
                    // Cálculos por unidad
                    margin: this.redondearDecimales(margen, 4),
                    saleCost: this.redondearDecimales(costoEquipo, 2),
                    equipmentPayment: this.redondearDecimales(pagoMensualEquipo, 2),
                    insurance: this.redondearDecimales(seguro, 2),
                    residualAmount: this.redondearDecimales(montoResidual, 2),
                    totalPayment: this.redondearDecimales(pagoMensualTotal, 2),
                    totalPaymentWithExtras: this.redondearDecimales(pagoMensualTotalConExtras, 2),
                    
                    // Placas y GPS
                    placasCost: this.redondearDecimales(costosPlacas, 2),
                    gpsCost: this.redondearDecimales(costosGPS, 2),
                    placasMonthlyPayment: this.redondearDecimales(pagoMensualPlacas, 2),
                    gpsMonthlyPayment: this.redondearDecimales(pagoMensualGPS, 2),
                    placasGpsMonthlyPayment: this.redondearDecimales(pagoMensualPlacasGPS, 2),
                    
                    // Desglose mensual - usando redondeo hacia abajo
                    monthlyEquipmentPayment: this.redondearHaciaAbajo(subtotalMensual, 2),
                    monthlySubtotal: this.redondearHaciaAbajo(subtotalCompleto, 2), // Subtotal completo con placas y GPS
                    monthlyIVA: this.redondearHaciaAbajo(ivaCompleto, 2), // IVA sobre subtotal completo
                    totalMonthlyPayment: this.redondearHaciaAbajo(pagoMensualTotalConExtras, 2), // Total completo con placas y GPS
                    totalMonthlyPaymentWithExtrasCalculated: this.redondearHaciaAbajo(pagoMensualFinal, 2), // Total completo recalculado
                    
                    // Comisión y residual  
                    totalCommission: this.redondearDecimales(comisionTotal, 2),
                    residualIVA: this.redondearDecimales(ivaResidual, 2),
                    residual1Payment: this.redondearDecimales(pagoResidual1, 2),
                    residual3Payments: this.redondearDecimales(pagoResidual3, 2),
                    
                    // Cálculos totales
                    totalSaleCost: this.redondearDecimales(costoTotalEquipo, 2),
                    totalEquipmentPayment: this.redondearDecimales(pagoMensualEquipo * cantidad, 2),
                    totalInsurance: this.redondearDecimales(seguroTotal, 2),
                    totalMonthlyPaymentFinal: this.redondearHaciaAbajo(pagoMensualFinal, 2),
                    totalMonthlyPaymentWithExtrasFinal: this.redondearHaciaAbajo(pagoMensualFinalConExtras, 2),
                    totalCost: this.redondearDecimales(costoTotal, 2),
                    totalUtility: utilidadEquipo,
                    
                    // Información adicional
                    quantity: cantidad,
                    term: plazo,
                    rate: tasa,
                    residualPercentage: residual,
                    insuranceRate: tarifaSeguroFinal
                };
            },
            
            redondearDecimales: (valor, decimales) => {
                const factor = Math.pow(10, decimales);
                return Math.round(valor * factor) / factor;
            },
            
            redondearHaciaAbajo: (valor, decimales) => {
                const factor = Math.pow(10, decimales);
                return Math.floor(valor * factor) / factor;
            }
        };
    }

    obtenerCostosAdicionales() {
        // Obtener costos desde catálogos o usar valores por defecto
        if (this.catalogos && this.catalogos.additionalCosts) {
            const costos = {};
            this.catalogos.additionalCosts.forEach(costo => {
                costos[costo.codigo] = parseFloat(costo.costo);
            });
            return costos;
        }
        // Fallback a valores por defecto
        return { PLACAS: 4200, GPS: 3300 };
    }

    obtenerConfiguracionEquipo(nombreEquipo) {
        // Primero buscar el equipo actual en this.equipos para obtener configuración completa
        const equipoActual = this.equipos.find(eq => eq.nombre === nombreEquipo);
        if (equipoActual) {
            return {
                includeGPS: equipoActual.includeGPS || false,
                includePlacas: equipoActual.includePlacas || false,
                gpsCost: equipoActual.gpsCost || 0, // Costo específico del equipo
                placasCost: equipoActual.placasCost || 0, // Costo específico del equipo
                equipmentName: nombreEquipo // Para buscar tarifa de seguro
            };
        }
        
        // Fallback: obtener configuración del equipo desde catálogos
        if (this.catalogos && this.catalogos.equipment) {
            const equipoConfig = this.catalogos.equipment.find(equipo => equipo.nombre === nombreEquipo);
            if (equipoConfig) {
                return {
                    includeGPS: equipoConfig.incluirGPS === 1 || equipoConfig.incluirGPS === true,
                    includePlacas: equipoConfig.incluirPlacas === 1 || equipoConfig.incluirPlacas === true,
                    gpsCost: 0, // No hay costo específico en catálogo
                    placasCost: 0, // No hay costo específico en catálogo
                    equipmentName: nombreEquipo // Para buscar tarifa de seguro
                };
            }
        }
        
        // Fallback final: no incluir extras por defecto
        return { includeGPS: false, includePlacas: false, gpsCost: 0, placasCost: 0, equipmentName: nombreEquipo };
    }

    puedeEditar() {
        return window.CURRENT_USER.role === 'admin' || window.CURRENT_USER.role === 'vendor';
    }

    activarModoEdicion() {
        this.modoEdicion = true;
        this.cambiosPendientes = false;
        
        // Mostrar campos de edición y ocultar valores estáticos
        this.renderizarModoEdicion();
        
        // Cambiar botones
        document.getElementById('btn-editar').classList.add('hidden');
        document.getElementById('edit-mode-actions').classList.remove('hidden');
    }

    cancelarEdicion() {
        if (this.cambiosPendientes) {
            if (!confirm('¿Estás seguro de cancelar? Se perderán los cambios no guardados.')) {
                return;
            }
        }
        
        this.modoEdicion = false;
        this.cambiosPendientes = false;
        
        // Volver a renderizar con valores originales
        this.renderizarDetalle();
        
        // Cambiar botones
        document.getElementById('btn-editar').classList.remove('hidden');
        document.getElementById('edit-mode-actions').classList.add('hidden');
    }

    async guardarCambios() {
        try {
            // Recopilar cambios
            const cambios = this.recopilarCambios();
            
            if (Object.keys(cambios.cotizacion).length === 0 && cambios.equipos.length === 0) {
                alert('No hay cambios para guardar');
                return;
            }
            
            // Mostrar loader
            this.mostrarCargando(true);
            
            // Enviar cambios al servidor
            const respuesta = await fetch('api/actualizar_cotizacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cotizacionId: this.cotizacionId,
                    cambios: cambios
                })
            });
            
            const datos = await respuesta.json();
            
            if (datos.status === 'success') {
                alert('Cambios guardados exitosamente');
                
                // Recargar datos
                await this.cargarDetalle();
                
                // Salir del modo edición
                this.modoEdicion = false;
                this.cambiosPendientes = false;
                
                // Cambiar botones
                document.getElementById('btn-editar').classList.remove('hidden');
                document.getElementById('edit-mode-actions').classList.add('hidden');
            } else {
                alert('Error: ' + datos.message);
            }
        } catch (error) {
            console.error('Error guardando cambios:', error);
            alert('Error al guardar los cambios');
        } finally {
            this.mostrarCargando(false);
        }
    }

    recopilarCambios() {
        const cambios = {
            cotizacion: {},
            equipos: [],
            equiposNuevos: [], // New equipment to be inserted
            equiposEliminados: [] // Equipment IDs to be deleted
        };
        
        // Cambios en la cotización
        const tasa = parseFloat(document.getElementById('edit-tasa')?.value) / 100; // Convertir de porcentaje a decimal
        const residual = parseFloat(document.getElementById('edit-residual')?.value);
        
        // Obtener plazo desde select o input personalizado
        let plazo = 0;
        const selectPlazo = document.getElementById('edit-plazo');
        const inputPlazoPersonalizado = document.getElementById('edit-plazo-personalizado');
        
        if (selectPlazo && selectPlazo.value === 'OTRO') {
            // Usar valor del input personalizado
            plazo = parseInt(inputPlazoPersonalizado?.value) || 0;
        } else if (selectPlazo && selectPlazo.value) {
            // Usar valor del select (obtener meses del data-attribute)
            const opcionSeleccionada = selectPlazo.selectedOptions[0];
            plazo = opcionSeleccionada && opcionSeleccionada.dataset.months ? 
                    parseInt(opcionSeleccionada.dataset.months) : 
                    parseInt(selectPlazo.value);
        }
        
        if (tasa !== this.cotizacion.tasa) {
            cambios.cotizacion.tasa = tasa;
        }
        
        if (plazo !== this.cotizacion.plazo) {
            cambios.cotizacion.plazo = plazo;
        }
        
        if (residual !== this.cotizacion.porcentajeResidual) {
            cambios.cotizacion.porcentajeResidual = residual;
        }
        
        // Store original equipment IDs to track deletions
        const equiposOriginalesIds = this.equiposOriginales ? this.equiposOriginales.map(e => e.id) : [];
        const equiposActualesIds = this.equipos.filter(e => !e.id.toString().startsWith('new_')).map(e => e.id);
        
        // Find deleted equipment
        cambios.equiposEliminados = equiposOriginalesIds.filter(id => !equiposActualesIds.includes(id));
        
        // Incluir el total del contrato recalculado si hay cambios
        if (Object.keys(cambios.cotizacion).length > 0 && this.totalContratoActualizado) {
            cambios.cotizacion.totalContrato = this.totalContratoActualizado;
        }
        
        // Cambios en equipos - incluir todos los valores calculados
        this.equipos.forEach((equipo, index) => {
            const nuevaCantidad = parseInt(document.getElementById(`edit-cantidad-${index}`)?.value) || equipo.cantidad;
            const nuevoCosto = parseFloat(document.getElementById(`edit-costo-${index}`)?.value) || equipo.precio;
            
            // Usar valores actuales (pueden haber cambiado en modo edición)
            const tasaActual = tasa || this.cotizacion.tasa;
            const plazoActual = plazo || this.cotizacion.plazo;
            const residualActual = residual || this.cotizacion.porcentajeResidual;
            
            // Obtener configuración del equipo
            const equipoConfig = this.obtenerConfiguracionEquipo(equipo.nombre);
            
            // Obtener tarifa de seguro específica del equipo
            let tarifaSeguro = 0.006; // Valor por defecto
            if (this.catalogos && this.catalogos.equipment) {
                const equipoInfo = this.catalogos.equipment.find(eq => eq.nombre === equipo.nombre);
                if (equipoInfo && equipoInfo.tarifaSeguro) {
                    tarifaSeguro = parseFloat(equipoInfo.tarifaSeguro);
                }
            }
            
            // Recalcular valores con los nuevos datos
            const calculos = this.calculadora.calcularValoresEquipo(nuevoCosto, tasaActual, plazoActual, residualActual, tarifaSeguro, nuevaCantidad, equipoConfig);
            
            // Check if this is a new equipment (temporary ID starts with 'new_')
            if (equipo.id.toString().startsWith('new_')) {
                // This is a new equipment to be inserted
                cambios.equiposNuevos.push({
                    equipo: equipo.nombre,
                    marca: equipo.marca || '',
                    modelo: equipo.modelo || '',
                    cantidad: nuevaCantidad,
                    costo: nuevoCosto,
                    // Incluir valores calculados para insertar en Cotizacion_Detail
                    costoVenta: calculos.totalSaleCost,
                    pagoEquipo: calculos.totalEquipmentPayment,
                    seguro: calculos.totalInsurance,
                    margen: calculos.margin,
                    valorResidual: calculos.residualAmount * nuevaCantidad,
                    ivaResidual: calculos.residualIVA * nuevaCantidad,
                    residual1Pago: calculos.residual1Payment * nuevaCantidad,
                    residual3Pagos: calculos.residual3Payments * nuevaCantidad,
                    tarifaSeguro: tarifaSeguro,
                    // Valores de placas y GPS
                    placasCost: calculos.placasCost,
                    gpsCost: calculos.gpsCost,
                    placasMonthlyPayment: calculos.placasMonthlyPayment,
                    gpsMonthlyPayment: calculos.gpsMonthlyPayment
                });
            } else if (nuevaCantidad !== equipo.cantidad || nuevoCosto !== equipo.precio || Object.keys(cambios.cotizacion).length > 0) {
                // This is an existing equipment with changes
                cambios.equipos.push({
                    id: equipo.id,
                    cantidad: nuevaCantidad,
                    costo: nuevoCosto,
                    // Incluir valores calculados para actualizar Cotizacion_Detail
                    costoVenta: calculos.totalSaleCost,
                    pagoEquipo: calculos.totalEquipmentPayment,
                    seguro: calculos.totalInsurance,
                    margen: calculos.margin,
                    valorResidual: calculos.residualAmount * nuevaCantidad,
                    ivaResidual: calculos.residualIVA * nuevaCantidad,
                    residual1Pago: calculos.residual1Payment * nuevaCantidad,
                    residual3Pagos: calculos.residual3Payments * nuevaCantidad,
                    tarifaSeguro: tarifaSeguro,
                    // Valores de placas y GPS
                    placasCost: calculos.placasCost,
                    gpsCost: calculos.gpsCost,
                    placasMonthlyPayment: calculos.placasMonthlyPayment,
                    gpsMonthlyPayment: calculos.gpsMonthlyPayment
                });
            }
        });
        
        // Si hay cambios en equipos también incluir total del contrato
        if ((cambios.equipos.length > 0 || cambios.equiposNuevos.length > 0 || cambios.equiposEliminados.length > 0) && this.totalContratoActualizado) {
            cambios.cotizacion.totalContrato = this.totalContratoActualizado;
        }
        
        return cambios;
    }

    renderizarModoEdicion() {
        // Renderizar campos editables para la cotización
        this.renderizarCamposEditablesCotizacion();
        
        // Renderizar campos editables para equipos
        this.renderizarCamposEditablesEquipos();
        
        // Vincular eventos de cambio para recálculo en vivo
        this.vincularEventosEdicion();
    }

    renderizarCamposEditablesCotizacion() {
        // Tasa
        const tasaInfo = document.getElementById('tasa-interes');
        if (tasaInfo) {
            tasaInfo.innerHTML = `
                <input type="number" id="edit-tasa" value="${(this.cotizacion.tasa * 100).toFixed(2)}" 
                       step="0.01" min="0.1" max="50" 
                       class="w-20 px-2 py-1 border rounded text-sm">%
            `;
        }
        
        // Plazo
        const plazoInfo = document.getElementById('plazo-contrato');
        if (plazoInfo) {
            const selectPlazo = this.crearSelectPlazo();
            plazoInfo.innerHTML = `
                ${selectPlazo}
                <div id="contenedor-plazo-personalizado-edit" class="hidden mt-2">
                    <input type="number" id="edit-plazo-personalizado" value="${this.cotizacion.plazo}" 
                           step="1" min="12" max="36" placeholder="12-36" 
                           class="w-20 px-2 py-1 border rounded text-sm"> meses
                </div>
            `;
        }
        
        // Residual
        const residualInfo = document.getElementById('porcentaje-residual');
        if (residualInfo) {
            residualInfo.innerHTML = `
                <input type="number" id="edit-residual" value="${this.cotizacion.porcentajeResidual}" 
                       step="0.1" min="10" max="25" 
                       class="w-20 px-2 py-1 border rounded text-sm">%
            `;
        }
    }

    renderizarCamposEditablesEquipos() {
        const container = document.getElementById('lista-equipos');
        container.innerHTML = '';

        this.equipos.forEach((equipo, index) => {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg p-4 shadow-sm border relative';
            
            // Obtener configuración del equipo para GPS/Placas
            const equipoConfig = this.obtenerConfiguracionEquipo(equipo.nombre);
            
            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">${equipo.nombre}</h3>
                        <p class="text-sm text-gray-500">${equipo.marca}${equipo.modelo ? ` - ${equipo.modelo}` : ''}</p>
                        <div class="text-xs text-gray-500 mt-1">
                            ${this.generarDescripcionExtras({includeGPS: equipoConfig.includeGPS, includePlacas: equipoConfig.includePlacas})}
                        </div>
                    </div>
                    ${this.puedeEditar() ? `
                        <button onclick="window.detalleCotizacion.eliminarEquipo(${index})" 
                                class="text-red-500 hover:text-red-700 text-sm px-2 py-1 rounded hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    ` : ''}
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Cantidad:</label>
                        <input type="number" id="edit-cantidad-${index}" value="${equipo.cantidad}" 
                               min="1" max="10" step="1"
                               class="w-full px-2 py-1 border rounded text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Precio unitario:</label>
                        <input type="number" id="edit-costo-${index}" value="${equipo.precio}" 
                               min="0.01" step="0.01"
                               class="w-full px-2 py-1 border rounded text-sm font-medium">
                    </div>
                </div>
                
                <!-- Wizard-style breakdown -->
                <div class="bg-gray-50 rounded-lg p-3" id="breakdown-${index}">
                    <div class="text-sm font-medium text-gray-700 mb-2">Desglose mensual (wizard-style):</div>
                    <div class="space-y-1 text-sm" id="breakdown-content-${index}">
                        <!-- Dynamic breakdown content will be inserted here -->
                    </div>
                </div>
            `;
            
            container.appendChild(card);
        });
        
        // Add "Add Equipment" button if under 10 equipment limit and user can edit
        if (this.equipos.length < 10 && this.puedeEditar()) {
            this.agregarBotonAgregarEquipo(container);
        }
        
        // Agregar sección de resumen de equipos también en modo edición
        this.renderizarResumenEquipos(container);
    }

    crearSelectPlazo() {
        if (!this.catalogos || !this.catalogos.terms) {
            return `<input type="number" id="edit-plazo" value="${this.cotizacion.plazo}" 
                           step="1" min="12" max="36" 
                           class="w-20 px-2 py-1 border rounded text-sm"> meses`;
        }

        let opciones = '<option value="">Seleccionar</option>';
        
        this.catalogos.terms.forEach(plazo => {
            const isSelected = plazo.meses && plazo.meses === this.cotizacion.plazo;
            opciones += `<option value="${plazo.valor}" ${isSelected ? 'selected' : ''} data-months="${plazo.meses || ''}">${plazo.descripcion}</option>`;
        });

        return `<select id="edit-plazo" class="w-auto px-2 py-1 border rounded text-sm">${opciones}</select>`;
    }

    vincularEventosEdicion() {
        // Eventos para campos de cotización
        ['edit-tasa', 'edit-residual'].forEach(id => {
            const campo = document.getElementById(id);
            if (campo) {
                campo.addEventListener('input', () => {
                    this.cambiosPendientes = true;
                    this.recalcularTodo();
                });
            }
        });

        // Manejar select de plazo específicamente
        const selectPlazo = document.getElementById('edit-plazo');
        if (selectPlazo) {
            selectPlazo.addEventListener('change', (e) => {
                const valorSeleccionado = e.target.value;
                const contenedorPersonalizado = document.getElementById('contenedor-plazo-personalizado-edit');
                
                if (valorSeleccionado === 'OTRO') {
                    contenedorPersonalizado.classList.remove('hidden');
                    // Enfocar el input personalizado
                    setTimeout(() => {
                        const inputPersonalizado = document.getElementById('edit-plazo-personalizado');
                        if (inputPersonalizado) inputPersonalizado.focus();
                    }, 100);
                } else {
                    contenedorPersonalizado.classList.add('hidden');
                    // Usar el valor del select
                    const opcion = e.target.selectedOptions[0];
                    if (opcion && opcion.dataset.months) {
                        this.cambiosPendientes = true;
                        this.recalcularTodo();
                    }
                }
            });
        }

        // Manejar input de plazo personalizado
        const inputPlazoPersonalizado = document.getElementById('edit-plazo-personalizado');
        if (inputPlazoPersonalizado) {
            inputPlazoPersonalizado.addEventListener('input', () => {
                this.cambiosPendientes = true;
                this.recalcularTodo();
            });
        }
        
        // Eventos para campos de equipos
        this.equipos.forEach((_, index) => {
            const camposCantidad = document.getElementById(`edit-cantidad-${index}`);
            const camposCosto = document.getElementById(`edit-costo-${index}`);
            
            if (camposCantidad) {
                camposCantidad.addEventListener('input', () => {
                    this.cambiosPendientes = true;
                    this.recalcularEquipo(index);
                });
            }
            
            if (camposCosto) {
                camposCosto.addEventListener('input', () => {
                    this.cambiosPendientes = true;
                    this.recalcularEquipo(index);
                });
            }
        });
    }

    recalcularTodo() {
        this.equipos.forEach((_, index) => {
            this.recalcularEquipo(index);
        });
        this.recalcularTotales();
    }

    recalcularEquipo(index) {
        const equipo = this.equipos[index];
        const cantidad = parseInt(document.getElementById(`edit-cantidad-${index}`)?.value) || equipo.cantidad;
        const costo = parseFloat(document.getElementById(`edit-costo-${index}`)?.value) || equipo.precio;
        const tasa = (parseFloat(document.getElementById('edit-tasa')?.value) / 100) || this.cotizacion.tasa;
        const residual = parseFloat(document.getElementById('edit-residual')?.value) || this.cotizacion.porcentajeResidual;
        
        // Obtener plazo usando la misma lógica que recopilarCambios()
        let plazo = this.cotizacion.plazo;
        const selectPlazo = document.getElementById('edit-plazo');
        const inputPlazoPersonalizado = document.getElementById('edit-plazo-personalizado');
        
        if (selectPlazo && selectPlazo.value === 'OTRO') {
            // Usar valor del input personalizado
            plazo = parseInt(inputPlazoPersonalizado?.value) || this.cotizacion.plazo;
        } else if (selectPlazo && selectPlazo.value) {
            // Usar valor del select (obtener meses del data-attribute)
            const opcionSeleccionada = selectPlazo.selectedOptions[0];
            plazo = opcionSeleccionada && opcionSeleccionada.dataset.months ? 
                    parseInt(opcionSeleccionada.dataset.months) : 
                    this.cotizacion.plazo;
        }
        
        // Obtener configuración del equipo (GPS/Placas)
        const equipoConfig = this.obtenerConfiguracionEquipo(equipo.nombre);
        
        // Obtener tarifa de seguro específica del equipo
        let tarifaSeguro = 0.006; // Valor por defecto
        if (this.catalogos && this.catalogos.equipment) {
            const equipoInfo = this.catalogos.equipment.find(eq => eq.nombre === equipo.nombre);
            if (equipoInfo && equipoInfo.tarifaSeguro) {
                tarifaSeguro = parseFloat(equipoInfo.tarifaSeguro);
            }
        }
        
        // Calcular nuevos valores con configuración correcta de GPS/Placas y tarifa de seguro específica
        const calculos = this.calculadora.calcularValoresEquipo(costo, tasa, plazo, residual, tarifaSeguro, cantidad, equipoConfig);
        
        // Actualizar los datos del equipo con los nuevos cálculos
        this.equipos[index] = {
            ...equipo,
            cantidad: cantidad,
            precio: costo,
            pagoEquipo: calculos.equipmentPayment,
            seguro: calculos.insurance,
            placasCost: calculos.placasCost,
            gpsCost: calculos.gpsCost,
            placasMonthlyPayment: calculos.placasMonthlyPayment,
            gpsMonthlyPayment: calculos.gpsMonthlyPayment,
            placasGpsMonthlyPayment: calculos.placasGpsMonthlyPayment
        };
        
        // Actualizar solo el total del equipo específico sin re-renderizar toda la lista
        this.actualizarTotalEquipo(index, cantidad, costo);
        
        // Mostrar cálculo en vivo para este equipo
        this.mostrarCalculoEnVivo(index, calculos);
    }

    recalcularTotales() {
        // Solo recalcular si estamos en modo edición
        if (!this.modoEdicion) {
            console.log('🔍 DEBUG - No estamos en modo edición, evitando recálculo');
            return;
        }
        
        console.log('🔍 DEBUG - Recalculando totales en modo edición');

        let subtotalTotal = 0;
        let pagoMensualTotal = 0;
        let totalContratoTotal = 0;
        let valorResidualTotal = 0;
        let totalEquipoTotal = 0;
        let montoFinanciar = 0;

        // Obtener plazo actual (puede haber cambiado en modo edición)
        let plazoActual = this.cotizacion.plazo;
        const selectPlazo = document.getElementById('edit-plazo');
        const inputPlazoPersonalizado = document.getElementById('edit-plazo-personalizado');

        if (selectPlazo && selectPlazo.value === 'OTRO') {
            plazoActual = parseInt(inputPlazoPersonalizado?.value) || this.cotizacion.plazo;
        } else if (selectPlazo && selectPlazo.value) {
            const opcionSeleccionada = selectPlazo.selectedOptions[0];
            plazoActual = opcionSeleccionada && opcionSeleccionada.dataset.months ?
                    parseInt(opcionSeleccionada.dataset.months) :
                    this.cotizacion.plazo;
        }

        // Calcular totales desde this.equipos usando pagoEquipo + seguro
        this.equipos.forEach((equipo, index) => {
            // Subtotal mensual por equipo: pagoEquipo + seguro
            const subtotalEquipoMensual = (equipo.pagoEquipo || 0) + (equipo.seguro || 0);
            const ivaEquipoMensual = subtotalEquipoMensual * 0.16;
            const pagoMensualEquipo = subtotalEquipoMensual + ivaEquipoMensual;

            // Multiplicar por cantidad de equipos
            subtotalTotal += subtotalEquipoMensual * equipo.cantidad;
            pagoMensualTotal += pagoMensualEquipo * equipo.cantidad;
            totalEquipoTotal += equipo.precio * equipo.cantidad;
            valorResidualTotal += (equipo.valorResidual || 0);

            // Total del contrato = pago equipo (SIN seguro, SIN IVA) × plazo × cantidad
            totalContratoTotal += (equipo.pagoEquipo || 0) * plazoActual * equipo.cantidad;

            // Calcular monto a financiar: (precio + GPS + Placas) × cantidad (SIN IVA)
            const costoGps = equipo.costoGpsAgregado || 0;
            const costoPlacas = equipo.costoPlacasAgregado || 0;
            const costoBaseEquipo = equipo.precio + costoGps + costoPlacas;
            montoFinanciar += costoBaseEquipo * equipo.cantidad;
        });
        
        // Calcular IVA total
        const ivaTotal = subtotalTotal * 0.16;
        
        // Actualizar totales en la interfaz
        const subtotalElement = document.getElementById('subtotal-valor');
        const ivaElement = document.getElementById('iva-valor');
        const totalEquipoElement = document.getElementById('total-equipo');
        const pagoMensualElement = document.getElementById('pago-mensual');
        const totalContratoElement = document.getElementById('total-contrato');
        const montoFinanciarElement = document.getElementById('monto-financiar');
        const valorResidualElement = document.getElementById('valor-residual');

        if (subtotalElement) {
            subtotalElement.textContent = this.formatearMoneda(subtotalTotal);
        }

        if (ivaElement) {
            ivaElement.textContent = this.formatearMoneda(ivaTotal);
        }

        if (totalEquipoElement) {
            totalEquipoElement.textContent = this.formatearMoneda(totalEquipoTotal);
        }

        if (pagoMensualElement) {
            pagoMensualElement.textContent = this.formatearMoneda(pagoMensualTotal);
        }

        // ACTUALIZAR EL TOTAL DEL CONTRATO - Aplicando wizard.js rounding
        const totalContratoFinal = Math.floor(totalContratoTotal * 100) / 100;
        if (totalContratoElement) {
            totalContratoElement.textContent = this.formatearMoneda(totalContratoFinal);
        }

        // Mostrar monto a financiar (SIN IVA)
        if (montoFinanciarElement) {
            montoFinanciarElement.textContent = this.formatearMoneda(montoFinanciar);
        }

        if (valorResidualElement) {
            valorResidualElement.textContent = this.formatearMoneda(valorResidualTotal);
        }

        // Guardar el total del contrato actualizado para enviarlo al backend
        this.totalContratoActualizado = totalContratoFinal;
    }

    calcularYMostrarTotalesBasicos() {
        console.log('🔍 DEBUG - Calculando totales básicos desde this.equipos:', this.equipos);

        let subtotalTotal = 0;
        let pagoMensualTotal = 0;
        let totalEquipoTotal = 0;
        let montoFinanciar = 0;
        let totalPagoEquipoSinSeguro = 0; // Para calcular total del contrato sin seguro

        // Calcular totales desde this.equipos usando pagoEquipo + seguro
        this.equipos.forEach((equipo, index) => {
            console.log(`🔍 DEBUG - Equipo ${index}:`, {
                nombre: equipo.nombre,
                cantidad: equipo.cantidad,
                precio: equipo.precio,
                pagoEquipo: equipo.pagoEquipo,
                seguro: equipo.seguro,
                costoGpsAgregado: equipo.costoGpsAgregado,
                costoPlacasAgregado: equipo.costoPlacasAgregado
            });

            // Subtotal mensual por equipo: pagoEquipo + seguro
            const subtotalEquipoMensual = (equipo.pagoEquipo || 0) + (equipo.seguro || 0);
            const ivaEquipoMensual = subtotalEquipoMensual * 0.16;
            const pagoMensualEquipo = subtotalEquipoMensual + ivaEquipoMensual;

            // Multiplicar por cantidad de equipos
            subtotalTotal += subtotalEquipoMensual * equipo.cantidad;
            pagoMensualTotal += pagoMensualEquipo * equipo.cantidad;
            totalEquipoTotal += equipo.precio * equipo.cantidad;
            totalPagoEquipoSinSeguro += (equipo.pagoEquipo || 0) * equipo.cantidad; // Solo equipo sin seguro

            // Calcular monto a financiar: (precio + GPS + Placas) × cantidad (SIN IVA)
            const costoGps = equipo.costoGpsAgregado || 0;
            const costoPlacas = equipo.costoPlacasAgregado || 0;
            const costoBaseEquipo = equipo.precio + costoGps + costoPlacas;
            montoFinanciar += costoBaseEquipo * equipo.cantidad;
        });
        
        // Calcular IVA total
        const ivaTotal = subtotalTotal * 0.16;

        // El "Total Equipo" debe ser subtotal + IVA (no solo el precio base)
        const totalEquipoConIva = subtotalTotal + ivaTotal;

        console.log('🔍 DEBUG - Totales básicos calculados:', {
            subtotalTotal,
            ivaTotal,
            pagoMensualTotal,
            totalEquipoTotal,
            totalEquipoConIva,
            montoFinanciar
        });

        // Actualizar totales en la interfaz
        document.getElementById('subtotal-valor').textContent = this.formatearMoneda(subtotalTotal);
        document.getElementById('iva-valor').textContent = this.formatearMoneda(ivaTotal);
        document.getElementById('total-equipo').textContent = this.formatearMoneda(totalEquipoConIva);
        document.getElementById('pago-mensual').textContent = this.formatearMoneda(pagoMensualTotal);

        // Mostrar anticipo si existe
        const anticipo = parseFloat(this.cotizacion.anticipo) || 0;
        const anticipoContainer = document.getElementById('anticipo-container');
        const anticipoElement = document.getElementById('anticipo');
        if (anticipo > 0) {
            if (anticipoContainer) {
                anticipoContainer.style.display = 'flex';
            }
            if (anticipoElement) {
                anticipoElement.textContent = this.formatearMoneda(anticipo);
            }
        } else {
            if (anticipoContainer) {
                anticipoContainer.style.display = 'none';
            }
        }

        // Mostrar monto a financiar (SIN IVA, restando anticipo)
        const montoFinanciarFinal = montoFinanciar - anticipo;
        const montoFinanciarElement = document.getElementById('monto-financiar');
        if (montoFinanciarElement) {
            montoFinanciarElement.textContent = this.formatearMoneda(montoFinanciarFinal);
        }

        // Calcular y mostrar total del contrato (SIN seguro, SIN IVA, NO restar anticipo)
        const totalContratoSinIVA = totalPagoEquipoSinSeguro * this.cotizacion.plazo;
        document.getElementById('total-contrato').textContent = this.formatearMoneda(totalContratoSinIVA);
    }

    actualizarTotalEquipo(index, cantidad, costo) {
        const totalElement = document.getElementById(`total-equipo-${index}`);
        if (totalElement) {
            totalElement.textContent = this.formatearMoneda(cantidad * costo);
        }
    }

    mostrarCalculoEnVivo(index, calculos) {
        const breakdownElement = document.getElementById(`breakdown-content-${index}`);
        if (breakdownElement) {
            // Use wizard-identical breakdown structure
            const equipoConfig = this.obtenerConfiguracionEquipo(this.equipos[index].nombre);
            const descripcionExtras = this.generarDescripcionExtras({
                includeGPS: equipoConfig.includeGPS, 
                includePlacas: equipoConfig.includePlacas
            });
            
            // CORRECCIÓN: Mostrar desglose como el wizard (GPS/Placas ya incluidos en el equipo)
            let html = `
                <div class="flex justify-between">
                    <span class="text-gray-600">Equipo ${descripcionExtras}:</span>
                    <span class="text-gray-900">${this.formatearMoneda(calculos.equipmentPayment || 0)}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Seguro:</span>
                    <span class="text-gray-900">${this.formatearMoneda(calculos.insurance || 0)}</span>
                </div>`;
                
            // Mostrar SOLO indicadores de que GPS/Placas están incluidos (NO como costos adicionales)
            if (equipoConfig.includeGPS) {
                html += `
                    <div class="flex justify-between">
                        <span class="text-green-600 text-xs">✓ GPS incluido en equipo</span>
                        <span class="text-green-600">—</span>
                    </div>`;
            }
            
            if (equipoConfig.includePlacas) {
                html += `
                    <div class="flex justify-between">
                        <span class="text-green-600 text-xs">✓ Placas incluidas en equipo</span>
                        <span class="text-green-600">—</span>
                    </div>`;
            }
            
            // El subtotal e IVA usan los valores ya calculados (que incluyen GPS/Placas en el costo base)
            const subtotalMensual = (calculos.equipmentPayment || 0) + (calculos.insurance || 0);
            const ivaMensual = subtotalMensual * 0.16;
            const totalMensual = subtotalMensual + ivaMensual;
            
            html += `
                <div class="flex justify-between border-t pt-1">
                    <span class="text-gray-700 font-medium">Subtotal:</span>
                    <span class="font-medium">${this.formatearMoneda(subtotalMensual)}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">IVA (16%):</span>
                    <span class="text-gray-900">${this.formatearMoneda(ivaMensual)}</span>
                </div>
                <div class="flex justify-between border-t pt-1 font-semibold">
                    <span class="text-accent">Total mensual:</span>
                    <span class="text-accent">${this.formatearMoneda(totalMensual)}</span>
                </div>
                
                <!-- Separador para residuales -->
                <div class="border-t border-gray-300 pt-2 mt-2">
                    <div class="text-xs font-medium text-blue-700 mb-1">Valores residuales:</div>
                    <div class="flex justify-between text-xs">
                        <span class="text-blue-600">Residual base:</span>
                        <span class="text-blue-900">${this.formatearMoneda(calculos.residualAmount || 0)}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-blue-600">1 pago:</span>
                        <span class="text-blue-900">${this.formatearMoneda(calculos.residual1Payment || 0)}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-blue-600">3 pagos (c/u):</span>
                        <span class="text-blue-900">${this.formatearMoneda(calculos.residual3Payments || 0)}</span>
                    </div>
                </div>`;
                
            breakdownElement.innerHTML = html;
        }
    }

    mostrarCargando(mostrar) {
        const loader = document.getElementById('loading-overlay');
        if (loader) {
            if (mostrar) {
                loader.classList.remove('hidden');
            } else {
                loader.classList.add('hidden');
            }
        }
    }

    redondearDecimales(valor, decimales) {
        const factor = Math.pow(10, decimales);
        return Math.round(valor * factor) / factor;
    }

    generarDescripcionExtras(calculations) {
        const incluye = [];
        
        if (calculations && calculations.includePlacas) {
            incluye.push('Placas');
        }
        
        if (calculations && calculations.includeGPS) {
            incluye.push('GPS');
        }
        
        if (incluye.length === 0) {
            return '';
        } else if (incluye.length === 1) {
            return `(${incluye[0]} incluido)`;
        } else {
            return `(${incluye.join(' y ')} incluidos)`;
        }
    }

    generarDesgloseMensualDetalle(equipo, pagoMensualEquipo, calculations) {
        let html = '';

        // Usar valores de GPS y Placas desde la BD (ya calculados)
        const pagoGPS = parseFloat(equipo.gpsMonthlyPayment) || 0;
        const pagoPlacas = parseFloat(equipo.placasMonthlyPayment) || 0;

        // Calcular equipo sin GPS ni Placas
        const equipoSinExtras = pagoMensualEquipo - pagoGPS - pagoPlacas;

        // Mostrar Equipo (sin GPS ni Placas)
        html += `
            <div class="flex justify-between">
                <span class="text-gray-600">Equipo:</span>
                <span class="text-gray-900">${this.formatearMoneda(this.redondearHaciaAbajo(equipoSinExtras, 2))}</span>
            </div>
        `;

        // Mostrar GPS si está incluido (solo si pagoGPS > 0)
        if (pagoGPS > 0) {
            html += `
                <div class="flex justify-between">
                    <span class="text-gray-600">GPS:</span>
                    <span class="text-gray-900">${this.formatearMoneda(this.redondearHaciaAbajo(pagoGPS, 2))}</span>
                </div>
            `;
        }

        // Mostrar Placas si están incluidas (solo si pagoPlacas > 0)
        if (pagoPlacas > 0) {
            html += `
                <div class="flex justify-between">
                    <span class="text-gray-600">Placas:</span>
                    <span class="text-gray-900">${this.formatearMoneda(this.redondearHaciaAbajo(pagoPlacas, 2))}</span>
                </div>
            `;
        }

        return html;
    }

    generarDesgloseExtras(equipo, calculations) {
        let desglose = '';

        // CORRECCIÓN: Mostrar GPS/Placas como INCLUIDOS en el equipo (no como costos adicionales)
        if (calculations && calculations.includeGPS) {
            desglose += `
                <div class="flex justify-between">
                    <span class="text-green-600 text-xs">✓ GPS incluido en equipo</span>
                    <span class="text-green-600">—</span>
                </div>
            `;
        }

        if (calculations && calculations.includePlacas) {
            desglose += `
                <div class="flex justify-between">
                    <span class="text-green-600 text-xs">✓ Placas incluidas en equipo</span>
                    <span class="text-green-600">—</span>
                </div>
            `;
        }

        return desglose;
    }

    redondearHaciaAbajo(valor, decimales) {
        const factor = Math.pow(10, decimales);
        return Math.floor(valor * factor) / factor;
    }

    // Equipment Management Methods
    agregarBotonAgregarEquipo(container) {
        const addButton = document.createElement('div');
        addButton.className = 'bg-white border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors cursor-pointer';
        addButton.innerHTML = `
            <button onclick="window.detalleCotizacion.mostrarModalAgregarEquipo()" 
                    class="text-gray-500 hover:text-gray-700">
                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="text-sm font-medium">Agregar Equipo</span>
                <div class="text-xs text-gray-400 mt-1">(${this.equipos.length}/10 equipos)</div>
            </button>
        `;
        container.appendChild(addButton);
    }

    mostrarModalAgregarEquipo() {
        if (this.equipos.length >= 10) {
            alert('Máximo 10 equipos permitidos por cotización');
            return;
        }

        // Create modal HTML with equipment selection form
        const modalHtml = `
            <div id="modal-agregar-equipo" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Agregar Equipo</h3>
                        <button onclick="window.detalleCotizacion.cerrarModalAgregarEquipo()" 
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <form id="form-agregar-equipo" class="space-y-4">
                        <!-- Equipment Type Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Equipo</label>
                            <select id="modal-tipo-equipo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleccionar tipo de equipo...</option>
                            </select>
                        </div>
                        
                        <!-- Brand Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                            <select id="modal-marca" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleccionar marca...</option>
                            </select>
                        </div>
                        
                        <!-- Model Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                            <select id="modal-modelo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleccionar modelo...</option>
                            </select>
                        </div>
                        
                        <!-- Cost -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Costo Base</label>
                            <input type="number" id="modal-costo" step="0.01" min="1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Ingrese el costo del equipo">
                        </div>
                        
                        <!-- Quantity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                            <input type="number" id="modal-cantidad" step="1" min="1" max="10" value="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Equipment Configuration Preview -->
                        <div id="modal-equipo-config" class="bg-gray-50 p-3 rounded-lg hidden">
                            <div class="text-sm font-medium text-gray-700 mb-2">Configuración del equipo:</div>
                            <div id="modal-config-details" class="text-sm text-gray-600"></div>
                        </div>
                    </form>
                    
                    <div class="flex justify-end mt-6 gap-3">
                        <button onclick="window.detalleCotizacion.cerrarModalAgregarEquipo()" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button onclick="window.detalleCotizacion.agregarEquipoDesdeModal()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                                id="btn-agregar-equipo" disabled>
                            Agregar Equipo
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Load catalogs and setup form
        this.setupModalAgregarEquipo();
    }

    cerrarModalAgregarEquipo() {
        const modal = document.getElementById('modal-agregar-equipo');
        if (modal) {
            modal.remove();
        }
    }

    setupModalAgregarEquipo() {
        // Load equipment types
        this.cargarTiposEquipoModal();
        
        // Load brands
        this.cargarMarcasModal();
        
        // Setup event listeners
        this.setupModalEventListeners();
    }

    cargarTiposEquipoModal() {
        const selectTipoEquipo = document.getElementById('modal-tipo-equipo');
        
        if (!this.catalogos || !this.catalogos.equipment) {
            selectTipoEquipo.innerHTML = '<option value="">Error cargando tipos de equipo</option>';
            return;
        }

        // Get unique equipment types
        const tiposUnicos = [...new Set(this.catalogos.equipment.map(eq => eq.nombre))];
        
        selectTipoEquipo.innerHTML = '<option value="">Seleccionar tipo de equipo...</option>';
        tiposUnicos.forEach(tipo => {
            selectTipoEquipo.innerHTML += `<option value="${tipo}">${tipo}</option>`;
        });
    }

    cargarMarcasModal() {
        const selectMarca = document.getElementById('modal-marca');
        
        if (!this.catalogos || !this.catalogos.brands) {
            selectMarca.innerHTML = '<option value="">Error cargando marcas</option>';
            return;
        }

        selectMarca.innerHTML = '<option value="">Seleccionar marca...</option>';
        this.catalogos.brands.forEach(marca => {
            selectMarca.innerHTML += `<option value="${marca.nombre}">${marca.nombre}</option>`;
        });
    }

    cargarModelosModal(equipoSeleccionado) {
        const selectModelo = document.getElementById('modal-modelo');
        
        if (!this.catalogos || !this.catalogos.models || !equipoSeleccionado) {
            selectModelo.innerHTML = '<option value="">Seleccionar modelo...</option>';
            return;
        }

        const modelosParaEquipo = this.catalogos.models.filter(model => 
            model.equipo === equipoSeleccionado
        );

        selectModelo.innerHTML = '<option value="">Seleccionar modelo...</option>';
        modelosParaEquipo.forEach(modelo => {
            selectModelo.innerHTML += `<option value="${modelo.descripcion}">${modelo.descripcion}</option>`;
        });
    }

    setupModalEventListeners() {
        const tipoEquipo = document.getElementById('modal-tipo-equipo');
        const marca = document.getElementById('modal-marca');
        const modelo = document.getElementById('modal-modelo');
        const costo = document.getElementById('modal-costo');
        const cantidad = document.getElementById('modal-cantidad');

        // Equipment type change
        tipoEquipo.addEventListener('change', (e) => {
            this.cargarModelosModal(e.target.value);
            this.mostrarConfiguracionEquipo(e.target.value);
            this.validarFormularioModal();
        });

        // Form validation listeners
        [marca, modelo, costo, cantidad].forEach(input => {
            input.addEventListener('input', () => this.validarFormularioModal());
            input.addEventListener('change', () => this.validarFormularioModal());
        });
    }

    mostrarConfiguracionEquipo(tipoEquipo) {
        const configContainer = document.getElementById('modal-equipo-config');
        const configDetails = document.getElementById('modal-config-details');

        if (!tipoEquipo || !this.catalogos || !this.catalogos.equipment) {
            configContainer.classList.add('hidden');
            return;
        }

        const equipoConfig = this.catalogos.equipment.find(eq => eq.nombre === tipoEquipo);
        
        if (!equipoConfig) {
            configContainer.classList.add('hidden');
            return;
        }

        const includeGPS = equipoConfig.incluirGPS === 1 || equipoConfig.incluirGPS === true;
        const includePlacas = equipoConfig.incluirPlacas === 1 || equipoConfig.incluirPlacas === true;
        const tarifaSeguro = parseFloat(equipoConfig.tarifaSeguro || 0.006);

        let configText = `Tarifa de seguro: ${(tarifaSeguro * 100).toFixed(3)}%`;
        
        if (includeGPS || includePlacas) {
            configText += ' | Incluye: ';
            const includes = [];
            if (includeGPS) includes.push('GPS');
            if (includePlacas) includes.push('Placas');
            configText += includes.join(' y ');
        }

        configDetails.textContent = configText;
        configContainer.classList.remove('hidden');
    }

    validarFormularioModal() {
        const tipoEquipo = document.getElementById('modal-tipo-equipo').value;
        const marca = document.getElementById('modal-marca').value;
        const modelo = document.getElementById('modal-modelo').value;
        const costo = parseFloat(document.getElementById('modal-costo').value);
        const cantidad = parseInt(document.getElementById('modal-cantidad').value);

        const btnAgregar = document.getElementById('btn-agregar-equipo');
        
        const esValido = tipoEquipo && marca && modelo && costo > 0 && cantidad > 0 && cantidad <= 10;
        
        btnAgregar.disabled = !esValido;
    }

    agregarEquipoDesdeModal() {
        const tipoEquipo = document.getElementById('modal-tipo-equipo').value;
        const marca = document.getElementById('modal-marca').value;
        const modelo = document.getElementById('modal-modelo').value;
        const costo = parseFloat(document.getElementById('modal-costo').value);
        const cantidad = parseInt(document.getElementById('modal-cantidad').value);

        // Validations
        if (!tipoEquipo || !marca || !modelo || !costo || !cantidad) {
            alert('Por favor complete todos los campos');
            return;
        }

        if (costo <= 0) {
            alert('El costo debe ser mayor a 0');
            return;
        }

        if (cantidad < 1 || cantidad > 10) {
            alert('La cantidad debe estar entre 1 y 10');
            return;
        }

        // Get equipment configuration
        const equipoConfig = this.catalogos.equipment.find(eq => eq.nombre === tipoEquipo);
        
        // Create new equipment object
        const nuevoEquipo = {
            id: `new_${Date.now()}`, // Temporary ID for new equipment
            nombre: tipoEquipo,
            marca: marca,
            modelo: modelo,
            cantidad: cantidad,
            precio: costo,
            // GPS/Placas configuration from catalog
            includeGPS: equipoConfig ? (equipoConfig.incluirGPS === 1 || equipoConfig.incluirGPS === true) : false,
            includePlacas: equipoConfig ? (equipoConfig.incluirPlacas === 1 || equipoConfig.incluirPlacas === true) : false,
            // Default calculated values (will be recalculated)
            pagoEquipo: 0,
            seguro: 0,
            placasCost: 0,
            gpsCost: 0,
            placasMonthlyPayment: 0,
            gpsMonthlyPayment: 0,
            placasGpsMonthlyPayment: 0
        };

        // Add to equipment list
        this.equipos.push(nuevoEquipo);
        this.cambiosPendientes = true;

        // Close modal
        this.cerrarModalAgregarEquipo();

        // Re-render edit mode with new equipment
        this.renderizarCamposEditablesEquipos();
        this.vincularEventosEdicion();
        
        // Recalculate with new equipment
        this.recalcularTodo();

        // Show success message
        this.mostrarMensajeTemporal('Equipo agregado exitosamente', 'success');
    }

    eliminarEquipo(index) {
        if (this.equipos.length <= 1) {
            alert('Debe mantener al menos un equipo en la cotización');
            return;
        }

        const equipo = this.equipos[index];
        const confirmMessage = `¿Estás seguro de eliminar "${equipo.nombre}" de la cotización?`;
        
        if (confirm(confirmMessage)) {
            // Remove equipment from array
            this.equipos.splice(index, 1);
            this.cambiosPendientes = true;

            // Re-render edit mode
            this.renderizarCamposEditablesEquipos();
            this.vincularEventosEdicion();
            
            // Recalculate totals
            this.recalcularTodo();

            // Show success message
            this.mostrarMensajeTemporal('Equipo eliminado exitosamente', 'success');
        }
    }

    mostrarMensajeTemporal(mensaje, tipo = 'info') {
        // Create temporary message
        const messageDiv = document.createElement('div');
        messageDiv.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg text-white font-medium ${
            tipo === 'success' ? 'bg-green-500' : 
            tipo === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        messageDiv.textContent = mensaje;

        document.body.appendChild(messageDiv);

        // Remove after 3 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 3000);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.detalleCotizacion = new DetalleCotizacion();
});