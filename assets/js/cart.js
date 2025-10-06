// Controlador de Carrito - Funcionalidad del panel lateral
class ControladorCarrito {
    constructor() {
        this.estaAbierto = false;
        this.equipos = [];
        this.totales = { contrato: 0, utilidad: 0 };
        
        this.inicializar();
    }

    inicializar() {
        this.configurarEscuchadores();
    }

    configurarEscuchadores() {
        // Botón de toggle del carrito
        document.getElementById('alternar-carrito').addEventListener('click', () => this.alternarCarrito());
        
        // Botones para cerrar carrito
        document.getElementById('cerrar-carrito').addEventListener('click', () => this.cerrarCarrito());
        document.getElementById('superposicion-carrito').addEventListener('click', () => this.cerrarCarrito());
        
        // Prevenir que los clics en el contenido cierren el panel
        document.getElementById('contenido-carrito').addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Manejar tecla escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.estaAbierto) {
                this.cerrarCarrito();
            }
        });
    }

    alternarCarrito() {
        if (this.estaAbierto) {
            this.cerrarCarrito();
        } else {
            this.abrirCarrito();
        }
    }

    abrirCarrito() {
        const panel = document.getElementById('panel-carrito');
        const contenido = document.getElementById('contenido-carrito');
        
        panel.classList.remove('hidden');
        panel.classList.add('show');
        
        // Activar animación
        setTimeout(() => {
            contenido.style.transform = 'translateX(0)';
        }, 10);
        
        this.estaAbierto = true;
        
        // Prevenir scroll del body cuando el carrito está abierto
        document.body.style.overflow = 'hidden';
    }

    cerrarCarrito() {
        const panel = document.getElementById('panel-carrito');
        const contenido = document.getElementById('contenido-carrito');
        
        contenido.style.transform = 'translateX(100%)';
        
        setTimeout(() => {
            panel.classList.remove('show');
            panel.classList.add('hidden');
        }, 300);
        
        this.estaAbierto = false;
        
        // Restaurar scroll del body
        document.body.style.overflow = '';
    }

    actualizarCarrito(equipos, totales, terminosGlobales = null) {
        this.equipos = equipos || [];
        this.totales = totales || { contrato: 0, utilidad: 0 };
        this.terminosGlobales = terminosGlobales;
        
        this.actualizarContadorCarrito();
        this.renderizarTerminosGlobales();
        this.renderizarElementosCarrito();
        this.actualizarTotalesCarrito();
    }

    actualizarContadorCarrito() {
        const elementoContador = document.getElementById('contador-carrito');
        const cantidad = this.equipos.length;
        
        if (cantidad > 0) {
            elementoContador.textContent = cantidad;
            elementoContador.classList.remove('hidden');
        } else {
            elementoContador.classList.add('hidden');
        }
    }

    renderizarTerminosGlobales() {
        const contenedor = document.getElementById('terminos-globales-carrito');
        
        if (!contenedor) {
            // Crear el contenedor si no existe
            this.crearContenedorTerminosGlobales();
            return;
        }
        
        if (this.terminosGlobales && (this.terminosGlobales.term > 0 || this.terminosGlobales.residual > 0)) {
            const html = `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">Términos globales</h4>
                    <div class="space-y-1 text-xs text-blue-700">
                        <div class="flex justify-between">
                            <span>Plazo:</span>
                            <span class="font-medium">${this.terminosGlobales.term > 0 ? this.terminosGlobales.term + ' meses' : 'No definido'}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Residual:</span>
                            <span class="font-medium">${this.terminosGlobales.residual}%</span>
                        </div>
                    </div>
                </div>
            `;
            contenedor.innerHTML = html;
            contenedor.classList.remove('hidden');
        } else {
            contenedor.innerHTML = '';
            contenedor.classList.add('hidden');
        }
    }

    crearContenedorTerminosGlobales() {
        // Crear el contenedor de términos globales en el encabezado del carrito
        const elementosCarrito = document.getElementById('elementos-carrito');
        if (elementosCarrito) {
            const contenedor = document.createElement('div');
            contenedor.id = 'terminos-globales-carrito';
            contenedor.className = 'hidden';
            elementosCarrito.parentNode.insertBefore(contenedor, elementosCarrito);
        }
    }

    renderizarElementosCarrito() {
        const contenedor = document.getElementById('elementos-carrito');
        
        if (this.equipos.length === 0) {
            contenedor.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m-2.4 2L7 13m0 0l-1.5 1.5M7 13l1.5 1.5"></path>
                    </svg>
                    <p class="text-gray-500 text-sm">No hay equipos agregados</p>
                    <p class="text-gray-400 text-xs mt-1">Los equipos aparecerán aquí al agregarlos</p>
                </div>
            `;
            return;
        }

        let html = '<div class="space-y-3">';
        
        this.equipos.forEach((elemento, indice) => {
            html += this.renderizarElementoCarrito(elemento, indice);
        });
        
        html += '</div>';
        contenedor.innerHTML = html;
    }

    renderizarElementoCarrito(elemento, indice) {
        return `
            <div class="bg-gray-50 rounded-lg p-3 cart-item" data-index="${indice}">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 text-sm">${elemento.quantity}x ${elemento.type}</h4>
                        <p class="text-xs text-gray-600">${elemento.brand}</p>
                        ${elemento.model ? `<p class="text-xs text-gray-500">${elemento.model}</p>` : ''}
                    </div>
                    <button onclick="controladorCarrito.removerDelCarrito(${indice})" class="text-red-500 hover:text-red-700 p-1 ml-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Costo unitario:</span>
                        <span class="text-gray-900">$${elemento.cost.toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Plazo:</span>
                        <span class="text-gray-900">${elemento.term} meses</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Equipo <span class="text-gray-400">${this.generarDescripcionExtras(elemento.calculations)}</span>:</span>
                        <span class="text-gray-900">$${(elemento.calculations.monthlyEquipmentPayment || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Seguro:</span>
                        <span class="text-gray-900">$${(elemento.calculations.monthlyInsurance || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 mt-1">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="text-gray-900">$${(elemento.calculations.monthlySubtotal || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">IVA (16%):</span>
                        <span class="text-gray-900">$${(elemento.calculations.monthlyIVA || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 mt-1">
                        <span class="font-medium text-gray-700">Pago mensual:</span>
                        <span class="font-medium text-gray-900">$${(elemento.calculations.totalPaymentWithExtras || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-1 mt-1">
                        <span class="font-medium text-gray-700">Total:</span>
                        <span class="font-bold text-gray-900">$${(elemento.calculations.totalSaleCost || 0).toLocaleString()}</span>
                    </div>
                </div>
                
                <button onclick="controladorCarrito.alternarDetallesElemento(${indice})" class="text-xs text-blue-600 hover:text-blue-800 mt-2 flex items-center">
                    <span class="details-toggle-text">Ver detalles</span>
                    <svg class="w-3 h-3 ml-1 details-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <div class="details-panel hidden mt-2 p-2 bg-white rounded border text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        ${window.CONFIG_PERMISOS?.puedeVerInformacionSensible ? `
                        <div>
                            <span class="text-gray-600">Margen:</span>
                            <span class="float-right">${elemento.calculations.margin}</span>
                        </div>` : ''}
                        <div>
                            <span class="text-gray-600">Seguro:</span>
                            <span class="float-right">$${elemento.calculations.totalInsurance.toLocaleString()}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Residual:</span>
                            <span class="float-right">$${elemento.calculations.residualAmount.toLocaleString()}</span>
                        </div>
                        ${window.CONFIG_PERMISOS?.puedeVerInformacionSensible ? `
                        <div>
                            <span class="text-gray-600">Utilidad:</span>
                            <span class="float-right text-green-600">$${((elemento.calculations.totalSaleCost - (elemento.cost * elemento.quantity))).toLocaleString()}</span>
                        </div>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    alternarDetallesElemento(indice) {
        const elementoCarrito = document.querySelector(`[data-index="${indice}"]`);
        const panelDetalles = elementoCarrito.querySelector('.details-panel');
        const textoToggle = elementoCarrito.querySelector('.details-toggle-text');
        const iconoToggle = elementoCarrito.querySelector('.details-toggle-icon');
        
        if (panelDetalles.classList.contains('hidden')) {
            panelDetalles.classList.remove('hidden');
            textoToggle.textContent = 'Ocultar detalles';
            iconoToggle.style.transform = 'rotate(180deg)';
        } else {
            panelDetalles.classList.add('hidden');
            textoToggle.textContent = 'Ver detalles';
            iconoToggle.style.transform = 'rotate(0deg)';
        }
    }

    removerDelCarrito(indice) {
        if (window.asistente) {
            window.asistente.removerEquipo(indice);
        }
    }

    actualizarTotalesCarrito() {
        document.getElementById('total-contrato-carrito').textContent = `$${this.totales.contrato.toLocaleString()}`;

        // Solo actualizar utilidad si el elemento existe (vendedores)
        const utilidadElemento = document.getElementById('total-utilidad-carrito');
        if (utilidadElemento) {
            utilidadElemento.textContent = `${((this.totales.utilidad || 0) * 100).toFixed(1)}%`;
        }
    }

    // Método público para obtener resumen del carrito para otros componentes
    obtenerResumenCarrito() {
        return {
            cantidadElementos: this.equipos.length,
            totalContrato: this.totales.contrato,
            totalUtilidad: this.totales.utilidad,
            equipos: this.equipos
        };
    }

    // Método para exportar datos del carrito
    exportarDatosCarrito() {
        return {
            equipos: this.equipos,
            totales: this.totales,
            exportadoEn: new Date().toISOString()
        };
    }

    // Método para limpiar carrito
    limpiarCarrito() {
        this.equipos = [];
        this.totales = { contrato: 0, utilidad: 0 };
        this.actualizarContadorCarrito();
        this.renderizarElementosCarrito();
        this.actualizarTotalesCarrito();
    }

    // Método para validar contenidos del carrito
    validarCarrito() {
        const problemas = [];
        
        if (this.equipos.length === 0) {
            problemas.push('No hay equipos en el carrito');
        }
        
        this.equipos.forEach((elemento, indice) => {
            if (!elemento.type || !elemento.brand) {
                problemas.push(`Equipo ${indice + 1}: Faltan datos básicos`);
            }
            
            if (!elemento.cost || elemento.cost <= 0) {
                problemas.push(`Equipo ${indice + 1}: Costo inválido`);
            }
            
            if (!elemento.term || elemento.term < 12 || elemento.term > 84) {
                problemas.push(`Equipo ${indice + 1}: Plazo inválido`);
            }
        });
        
        return {
            esValido: problemas.length === 0,
            problemas: problemas
        };
    }

    // Ayudantes de animación
    animarAgregarElemento() {
        const botonCarrito = document.getElementById('alternar-carrito');
        botonCarrito.classList.add('animate-pulse');
        
        setTimeout(() => {
            botonCarrito.classList.remove('animate-pulse');
        }, 600);
    }

    animarRemoverElemento() {
        // Agregar animación sutil al remover elementos
        const elementosCarrito = document.querySelectorAll('.cart-item');
        elementosCarrito.forEach(elemento => {
            elemento.style.transition = 'opacity 0.3s ease';
        });
    }

    generarDescripcionExtras(calculations) {
        const incluye = [];
        
        if (calculations.includePlacas) {
            incluye.push('Placas');
        }
        
        if (calculations.includeGPS) {
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
}

// Inicializar controlador de carrito inmediatamente
// console.log('🚀 Inicializando ControladorCarrito...');

function inicializarCarrito() {
    if (!window.controladorCarrito) {
        // console.log('📝 Creando nueva instancia de ControladorCarrito...');
        window.controladorCarrito = new ControladorCarrito();
        // console.log('✅ window.controladorCarrito creado:', window.controladorCarrito);
    }
}

if (document.readyState === 'loading') {
    // console.log('⏳ DOM aún cargando para carrito, esperando...');
    document.addEventListener('DOMContentLoaded', inicializarCarrito);
} else {
    // console.log('✅ DOM listo para carrito, inicializando...');
    inicializarCarrito();
}