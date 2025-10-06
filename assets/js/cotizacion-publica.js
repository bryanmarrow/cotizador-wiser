class DetallePublicoCotizacion {
    constructor(folio) {
        this.folio = folio;
        this.cotizacion = null;
        this.equipos = [];
        
        this.inicializar();
    }

    async inicializar() {
        await this.cargarDetalle();
    }

    async cargarDetalle() {
        try {
            const respuesta = await fetch(`api/obtener_detalle_publico.php?folio=${this.folio}`);
            const datos = await respuesta.json();

            if (datos.status === 'success') {
                this.cotizacion = datos.data.cotizacion;
                this.equipos = datos.data.equipos;
                this.renderizarDetalle();
            } else {
                this.mostrarError(datos.message);
            }
        } catch (error) {
            console.error('Error cargando detalle:', error);
            this.mostrarError('Error al cargar la cotización. Por favor, intente más tarde.');
        } finally {
            this.ocultarLoading();
        }
    }

    renderizarDetalle() {
        // Header info
        document.getElementById('cotizacion-numero').textContent = `#${this.cotizacion.id}`;
        document.getElementById('public-folio').textContent = this.folio;
        
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
        document.getElementById('porcentaje-residual').textContent = `${parseFloat(this.cotizacion.porcentajeResidual) || 0}%`;

        // Totales
        this.calcularYMostrarTotalesBasicos();
        document.getElementById('total-contrato').textContent = this.formatearMoneda(this.cotizacion.totalContrato);
        document.getElementById('total-residual-1pago').textContent = this.formatearMoneda(this.cotizacion.totalResidual1Pago);
        document.getElementById('total-residual-3pagos').textContent = this.formatearMoneda(this.cotizacion.totalResidual3Pagos);

        // Mostrar contenido principal
        document.getElementById('main-content').classList.remove('hidden');
    }

    renderizarEquipos() {
        const container = document.getElementById('lista-equipos');
        container.innerHTML = '';

        if (!this.equipos || this.equipos.length === 0) {
            container.innerHTML = `<div class="text-center py-6 text-gray-500">No se encontraron equipos.</div>`;
            return;
        }

        this.equipos.forEach((equipo, index) => {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg p-4 shadow-sm border';

            const cantidad = parseFloat(equipo.cantidad) || 0;
            const pagoMensualEquipo = parseFloat(equipo.pagoEquipo) || 0;
            const seguroSeparado = parseFloat(equipo.seguro) || 0;
            const pagoGPS = parseFloat(equipo.pagoGPS) || 0;
            const pagoPlacas = parseFloat(equipo.pagoPlacas) || 0;

            const subtotalMensual = pagoMensualEquipo + seguroSeparado + pagoGPS + pagoPlacas;
            const ivaMensual = subtotalMensual * 0.16;
            const totalMensual = subtotalMensual + ivaMensual;

            const tieneGPS = pagoGPS > 0;
            const tienePlacas = pagoPlacas > 0;

            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">${cantidad}x ${equipo.nombre}</h3>
                        <p class="text-sm text-gray-500">${equipo.marca}${equipo.modelo ? ` - ${equipo.modelo}` : ''}</p>
                        <p class="text-sm text-gray-500">Costo: ${this.formatearMoneda(equipo.precio)}</p>
                    </div>
                    <div class="text-right ml-3">
                        <div class="text-xs text-gray-500">Pago Mensual</div>
                        <div class="text-lg font-semibold text-accent">${this.formatearMoneda(totalMensual)}</div>
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-3 mb-3">
                    <div class="text-sm font-medium text-gray-700 mb-2">Desglose mensual por equipo:</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Equipo:</span>
                            <span class="text-gray-900">${this.formatearMoneda(pagoMensualEquipo)}</span>
                        </div>
                        ${tieneGPS ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">GPS:</span>
                            <span class="text-gray-900">${this.formatearMoneda(pagoGPS)}</span>
                        </div>` : ''}
                        ${tienePlacas ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Placas:</span>
                            <span class="text-gray-900">${this.formatearMoneda(pagoPlacas)}</span>
                        </div>` : ''}
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
                
                <div class="bg-blue-50 rounded-lg p-3">
                    <div class="text-sm font-medium text-blue-700 mb-2">Valores residuales por equipo:</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-blue-600">Valor residual (${parseFloat(this.cotizacion.porcentajeResidual) || 0}%):</span>
                            <span class="text-blue-900">${this.formatearMoneda(equipo.valorResidual)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-blue-600">IVA residual:</span>
                            <span class="text-blue-900">${this.formatearMoneda(equipo.ivaResidual)}</span>
                        </div>
                        <div class="flex justify-between border-t border-blue-200 pt-1">
                            <span class="text-blue-600">1 pago:</span>
                            <span class="font-medium text-blue-900">${this.formatearMoneda(equipo.residual1Pago)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-blue-600">3 pagos:</span>
                            <span class="font-medium text-blue-900">${this.formatearMoneda((parseFloat(equipo.residual3Pagos) || 0) * 3)}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-blue-500">Por pago (3 pagos):</span>
                            <span class="text-blue-800">${this.formatearMoneda(equipo.residual3Pagos)}</span>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    calcularYMostrarTotalesBasicos() {
        let subtotalTotal = 0;
        let totalEquipoTotal = 0;

        this.equipos.forEach(equipo => {
            const cantidad = parseFloat(equipo.cantidad) || 0;
            const pagoMensualEquipo = parseFloat(equipo.pagoEquipo) || 0;
            const seguroSeparado = parseFloat(equipo.seguro) || 0;
            const pagoGPS = parseFloat(equipo.pagoGPS) || 0;
            const pagoPlacas = parseFloat(equipo.pagoPlacas) || 0;

            const subtotalEquipoMensual = pagoMensualEquipo + seguroSeparado + pagoGPS + pagoPlacas;
            subtotalTotal += subtotalEquipoMensual * cantidad;
            totalEquipoTotal += (parseFloat(equipo.precio) || 0) * cantidad;
        });

        const ivaTotal = subtotalTotal * 0.16;
        const totalConIva = subtotalTotal + ivaTotal;

        document.getElementById('subtotal-valor').textContent = this.formatearMoneda(subtotalTotal);
        document.getElementById('iva-valor').textContent = this.formatearMoneda(ivaTotal);
        document.getElementById('total-equipo').textContent = this.formatearMoneda(totalEquipoTotal);
        document.getElementById('pago-mensual').textContent = this.formatearMoneda(totalConIva);
    }

    formatearMoneda(valor) {
        const numero = parseFloat(valor);
        if (isNaN(numero)) {
            return '$0.00'; // Fallback for NaN
        }
        return '$' + numero.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    renderEstado(estado) {
        const colores = {
            'borrador': 'bg-yellow-100 text-yellow-800',
            'completada': 'bg-green-100 text-green-800',
            'impresa': 'bg-blue-100 text-blue-800'
        };
        const color = colores[estado] || 'bg-gray-100 text-gray-800';
        return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color}">${estado.charAt(0).toUpperCase() + estado.slice(1)}</span>`;
    }

    ocultarLoading() {
        document.getElementById('loading-state').classList.add('hidden');
    }

    mostrarError(mensaje) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-message').textContent = mensaje;
        document.getElementById('error-state').classList.remove('hidden');
    }
}