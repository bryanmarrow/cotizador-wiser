class GestorCotizaciones {
    constructor() {
        this.vistaActual = 'tabla'; // 'tabla' o 'cards'
        this.paginaActual = 1;
        this.registrosPorPagina = 10;
        this.ordenCampo = 'Id';
        this.ordenDireccion = 'DESC';
        this.filtros = {};
        this.usuarios = [];
        
        this.inicializar();
    }

    async inicializar() {
        this.vincularEventos();
        await this.cargarUsuarios();
        await this.cargarCotizaciones();
    }

    vincularEventos() {
        // Toggle de vista (mobile)
        document.getElementById('vista-tabla')?.addEventListener('click', () => this.cambiarVista('tabla'));
        document.getElementById('vista-cards')?.addEventListener('click', () => this.cambiarVista('cards'));
        
        // Toggle de vista (desktop)
        document.getElementById('vista-tabla-desktop')?.addEventListener('click', () => this.cambiarVista('tabla'));
        document.getElementById('vista-cards-desktop')?.addEventListener('click', () => this.cambiarVista('cards'));

        // Filtros
        document.getElementById('aplicar-filtros').addEventListener('click', () => this.aplicarFiltros());
        document.getElementById('limpiar-filtros').addEventListener('click', () => this.limpiarFiltros());

        // Enter en campos de filtro
        document.getElementById('filtro-cliente').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.aplicarFiltros();
        });

        // Ordenamiento en tabla
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => this.ordenarPor(th.dataset.sort));
        });

        // Paginación
        document.getElementById('pagina-anterior').addEventListener('click', () => this.paginaAnterior());
        document.getElementById('pagina-siguiente').addEventListener('click', () => this.paginaSiguiente());
    }

    async cargarUsuarios() {
        if (window.CURRENT_USER.role !== 'admin') return;

        try {
            const respuesta = await fetch('api/obtener_usuarios.php');
            const datos = await respuesta.json();

            if (datos.status === 'success') {
                this.usuarios = datos.data;
                this.llenarSelectUsuarios();
            }
        } catch (error) {
            console.error('Error cargando usuarios:', error);
        }
    }

    llenarSelectUsuarios() {
        const select = document.getElementById('filtro-usuario');
        if (!select) return;

        // Limpiar opciones existentes (excepto la primera)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }

        this.usuarios.forEach(usuario => {
            const option = document.createElement('option');
            option.value = usuario.id;
            option.textContent = `${usuario.nombre} (${usuario.rolDisplay})`;
            select.appendChild(option);
        });
    }

    cambiarVista(vista) {
        this.vistaActual = vista;
        
        // Botones móviles
        const btnTabla = document.getElementById('vista-tabla');
        const btnCards = document.getElementById('vista-cards');
        
        // Botones desktop
        const btnTablaDesktop = document.getElementById('vista-tabla-desktop');
        const btnCardsDesktop = document.getElementById('vista-cards-desktop');
        
        // Contenedores móviles
        const contenedorTabla = document.getElementById('contenedor-tabla');
        const contenedorCards = document.getElementById('contenedor-cards');
        
        // Contenedores desktop
        const contenedorTablaDesktop = document.getElementById('contenedor-tabla-desktop');
        const contenedorCardsDesktop = document.getElementById('contenedor-cards-desktop');

        if (vista === 'tabla') {
            // Actualizar botones móviles
            if (btnTabla) {
                btnTabla.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                btnTabla.classList.remove('text-gray-500');
            }
            if (btnCards) {
                btnCards.classList.add('text-gray-500');
                btnCards.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
            }
            
            // Actualizar botones desktop
            if (btnTablaDesktop) {
                btnTablaDesktop.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                btnTablaDesktop.classList.remove('text-gray-500');
            }
            if (btnCardsDesktop) {
                btnCardsDesktop.classList.add('text-gray-500');
                btnCardsDesktop.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
            }
            
            // Mostrar/ocultar contenedores móviles
            if (contenedorTabla) contenedorTabla.classList.remove('hidden');
            if (contenedorCards) contenedorCards.classList.add('hidden');
            
            // Mostrar/ocultar contenedores desktop
            if (contenedorTablaDesktop) contenedorTablaDesktop.classList.remove('hidden');
            if (contenedorCardsDesktop) contenedorCardsDesktop.classList.add('hidden');
            
        } else {
            // Actualizar botones móviles
            if (btnCards) {
                btnCards.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                btnCards.classList.remove('text-gray-500');
            }
            if (btnTabla) {
                btnTabla.classList.add('text-gray-500');
                btnTabla.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
            }
            
            // Actualizar botones desktop
            if (btnCardsDesktop) {
                btnCardsDesktop.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                btnCardsDesktop.classList.remove('text-gray-500');
            }
            if (btnTablaDesktop) {
                btnTablaDesktop.classList.add('text-gray-500');
                btnTablaDesktop.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
            }
            
            // Mostrar/ocultar contenedores móviles
            if (contenedorTabla) contenedorTabla.classList.add('hidden');
            if (contenedorCards) contenedorCards.classList.remove('hidden');
            
            // Mostrar/ocultar contenedores desktop
            if (contenedorTablaDesktop) contenedorTablaDesktop.classList.add('hidden');
            if (contenedorCardsDesktop) contenedorCardsDesktop.classList.remove('hidden');
        }
        
        // Re-render data in the newly visible view
        if (this.cotizacionesData) {
            console.log(`🔄 Re-rendering data for ${vista} view after view switch`);
            this.renderizarCotizaciones(this.cotizacionesData);
        }
    }

    obtenerFiltros() {
        return {
            cliente: document.getElementById('filtro-cliente').value,
            fecha_desde: document.getElementById('filtro-fecha-desde').value,
            fecha_hasta: document.getElementById('filtro-fecha-hasta').value,
            usuario: document.getElementById('filtro-usuario')?.value || '',
            estado: document.getElementById('filtro-estado').value
        };
    }

    aplicarFiltros() {
        this.filtros = this.obtenerFiltros();
        this.paginaActual = 1;
        this.cargarCotizaciones();
    }

    limpiarFiltros() {
        document.getElementById('filtro-cliente').value = '';
        document.getElementById('filtro-fecha-desde').value = '';
        document.getElementById('filtro-fecha-hasta').value = '';
        if (document.getElementById('filtro-usuario')) {
            document.getElementById('filtro-usuario').value = '';
        }
        document.getElementById('filtro-estado').value = '';
        
        this.filtros = {};
        this.paginaActual = 1;
        this.cargarCotizaciones();
    }

    ordenarPor(campo) {
        if (this.ordenCampo === campo) {
            this.ordenDireccion = this.ordenDireccion === 'DESC' ? 'ASC' : 'DESC';
        } else {
            this.ordenCampo = campo;
            this.ordenDireccion = 'DESC';
        }

        this.actualizarIconosOrden();
        this.cargarCotizaciones();
    }

    actualizarIconosOrden() {
        document.querySelectorAll('.sort-icon').forEach(icon => {
            icon.textContent = '↕';
        });

        const th = document.querySelector(`th[data-sort="${this.ordenCampo}"] .sort-icon`);
        if (th) {
            th.textContent = this.ordenDireccion === 'DESC' ? '↓' : '↑';
        }
    }

    paginaAnterior() {
        if (this.paginaActual > 1) {
            this.paginaActual--;
            this.cargarCotizaciones();
        }
    }

    paginaSiguiente() {
        this.paginaActual++;
        this.cargarCotizaciones();
    }

    irAPagina(pagina) {
        this.paginaActual = pagina;
        this.cargarCotizaciones();
    }

    async cargarCotizaciones() {
        this.mostrarLoading(true);

        try {
            const params = new URLSearchParams({
                page: this.paginaActual,
                limit: this.registrosPorPagina,
                sort: this.ordenCampo,
                direction: this.ordenDireccion,
                ...this.filtros
            });

            const respuesta = await fetch(`api/listar_cotizaciones.php?${params}`);
            
            // Manejar diferentes códigos de estado HTTP
            if (!respuesta.ok) {
                if (respuesta.status === 401) {
                    throw new Error('Sesión expirada o no autorizado');
                } else if (respuesta.status === 403) {
                    throw new Error('No tiene permisos para ver las cotizaciones');
                } else if (respuesta.status >= 500) {
                    throw new Error('Error interno del servidor. Intente más tarde.');
                } else {
                    throw new Error(`Error HTTP ${respuesta.status}: ${respuesta.statusText}`);
                }
            }
            
            const texto = await respuesta.text();
            
            // Intentar parsear como JSON
            let datos;
            try {
                datos = JSON.parse(texto);
            } catch (parseError) {
                console.error('Error parseando JSON:', parseError);
                console.error('Respuesta recibida:', texto.substring(0, 500));
                throw new Error('Respuesta inválida del servidor');
            }

            if (datos.status === 'success') {
                this.cotizacionesData = datos.data.cotizaciones; // Store data for view switching
                this.renderizarCotizaciones(datos.data.cotizaciones);
                this.actualizarPaginacion(datos.data.pagination);
                this.actualizarContador(datos.data.pagination.total_records);
            } else {
                this.mostrarError(datos.message || 'Error desconocido del servidor');
            }
        } catch (error) {
            console.error('Error cargando cotizaciones:', error);
            
            // Mostrar mensaje específico según el tipo de error
            let mensajeError = 'Error al cargar las cotizaciones';
            if (error.message.includes('autorizado') || error.message.includes('Sesión')) {
                mensajeError = 'Sesión expirada. Inicie sesión nuevamente.';
            } else if (error.message.includes('permisos')) {
                mensajeError = 'No tiene permisos para ver las cotizaciones';
            } else if (error.message.includes('servidor')) {
                mensajeError = 'Error del servidor. Intente más tarde.';
            } else if (error.message.includes('inválida')) {
                mensajeError = 'Error de comunicación con el servidor';
            }
            
            this.mostrarError(mensajeError);
        } finally {
            this.mostrarLoading(false);
        }
    }

    renderizarCotizaciones(cotizaciones) {
        // console.log(`🎯 renderizarCotizaciones called. Vista actual: ${this.vistaActual}, Cotizaciones: ${cotizaciones.length}`);
        
        if (this.vistaActual === 'tabla') {
            // console.log('📋 Renderizando en vista tabla...');
            this.renderizarMobile(cotizaciones);  // Mobile/tablet
            this.renderizarDesktopTabla(cotizaciones);  // Desktop
        } else {
            // console.log('🎴 Renderizando en vista cards...');
            this.renderizarCards(cotizaciones);  // Mobile/tablet
            this.renderizarDesktopCards(cotizaciones);  // Desktop
        }
    }

    renderizarMobile(cotizaciones) {
        // console.log('📋 renderizarMobile called with', cotizaciones.length, 'cotizaciones');
        const container = document.getElementById('lista-cotizaciones-mobile');
        
        if (!container) {
            console.error('❌ Container lista-cotizaciones-mobile not found!');
            return;
        }
        
        // console.log('✅ Container found, clearing content...');
        container.innerHTML = '';

        if (cotizaciones.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <div class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="font-medium mb-2">No hay cotizaciones</div>
                    <div class="text-sm text-gray-400 mb-4">No se encontraron cotizaciones con los filtros aplicados</div>
                    <button onclick="window.location.href='index.php'" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 flex items-center justify-center gap-2 mx-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Nueva Cotización
                    </button>
                </div>
            `;
            return;
        }

        // console.log('🔄 Starting to render', cotizaciones.length, 'cotizaciones in mobile view...');
        
        cotizaciones.forEach((cotizacion, index) => {
            // console.log(`📋 Rendering cotizacion ${index + 1}: #${cotizacion.id}`);
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg p-4 shadow-sm border table-animate';
            
            card.innerHTML = `
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">#${cotizacion.id}</h3>
                        <p class="text-sm text-gray-600">${cotizacion.fechaFormateada}</p>
                    </div>
                    ${this.renderEstado(cotizacion.estado)}
                </div>
                
                <div class="space-y-2 mb-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Cliente:</span>
                        <span class="text-sm font-medium text-gray-900 text-right">${cotizacion.cliente}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Vendedor:</span>
                        <span class="text-sm text-gray-900 text-right">${cotizacion.vendedor}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Total:</span>
                        <span class="font-semibold text-gray-900">${cotizacion.totalFormateado}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Equipos:</span>
                        <span class="text-sm text-gray-900">${cotizacion.cantidadEquipos}</span>
                    </div>
                </div>
                
                <div class="flex gap-2 pt-3 border-t">
                    ${this.renderAcciones(cotizacion)}
                </div>
            `;
            
            container.appendChild(card);
            // console.log(`✅ Cotizacion #${cotizacion.id} added to mobile container`);
        });
        
        // console.log(`🎯 Mobile rendering complete. Container now has ${container.children.length} children`);
    }

    renderizarCards(cotizaciones) {
        const container = document.getElementById('lista-cotizaciones-cards');
        container.innerHTML = '';

        if (cotizaciones.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <div class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </div>
                    <div class="font-medium mb-2">No hay cotizaciones</div>
                    <div class="text-sm text-gray-400 mb-4">No se encontraron cotizaciones con los filtros aplicados</div>
                    <button onclick="window.location.href='index.php'" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 flex items-center justify-center gap-2 mx-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Nueva Cotización
                    </button>
                </div>
            `;
            return;
        }

        cotizaciones.forEach(cotizacion => {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg p-4 shadow-sm border table-animate';
            
            card.innerHTML = `
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-900">#${cotizacion.id}</h3>
                    ${this.renderEstado(cotizacion.estado)}
                </div>
                
                <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                    <div>
                        <span class="text-gray-600">Cliente:</span>
                        <div class="font-medium text-gray-900">${cotizacion.cliente}</div>
                    </div>
                    <div>
                        <span class="text-gray-600">Vendedor:</span>
                        <div class="text-gray-900">${cotizacion.vendedor}</div>
                    </div>
                    <div>
                        <span class="text-gray-600">Fecha:</span>
                        <div class="text-gray-900">${cotizacion.fechaFormateada}</div>
                    </div>
                    <div>
                        <span class="text-gray-600">Total:</span>
                        <div class="font-semibold text-gray-900">${cotizacion.totalFormateado}</div>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    ${this.renderAcciones(cotizacion)}
                </div>
            `;
            
            container.appendChild(card);
        });
    }

    renderizarDesktopTabla(cotizaciones) {
        const container = document.getElementById('lista-cotizaciones-desktop');
        
        if (!container) {
            console.error('❌ Container lista-cotizaciones-desktop not found!');
            return;
        }
        
        container.innerHTML = '';

        if (cotizaciones.length === 0) {
            container.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 mb-4 text-gray-300">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="font-medium mb-2">No hay cotizaciones</div>
                            <div class="text-sm text-gray-400 mb-4">No se encontraron cotizaciones con los filtros aplicados</div>
                            <button onclick="window.location.href='index.php'" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Nueva Cotización
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        cotizaciones.forEach(cotizacion => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 table-row';
            
            row.innerHTML = `
                <td class="px-4 py-4 whitespace-nowrap">
                    <div>
                        <div class="text-sm font-medium text-gray-900">${cotizacion.cliente}</div>
                        <div class="text-xs text-gray-500">#${cotizacion.id}</div>
                    </div>
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${cotizacion.fechaFormateada}</div>
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    ${this.renderEstado(cotizacion.estado)}
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">${cotizacion.totalFormateado}</div>
                    <div class="text-xs text-gray-500">${cotizacion.cantidadEquipos} equipos</div>
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${cotizacion.vendedor}</div>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-right">
                    <div class="flex gap-1 justify-end">
                        ${this.renderAccionesDesktop(cotizacion)}
                    </div>
                </td>
            `;
            
            container.appendChild(row);
        });
    }

    renderizarDesktopCards(cotizaciones) {
        const container = document.getElementById('lista-cotizaciones-cards-desktop');
        
        if (!container) {
            console.error('❌ Container lista-cotizaciones-cards-desktop not found!');
            return;
        }
        
        container.innerHTML = '';

        if (cotizaciones.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-8 text-gray-500">
                    <div class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                    </div>
                    <div class="font-medium mb-2">No hay cotizaciones</div>
                    <div class="text-sm text-gray-400 mb-4">No se encontraron cotizaciones con los filtros aplicados</div>
                    <button onclick="window.location.href='index.php'" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 flex items-center justify-center gap-2 mx-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Nueva Cotización
                    </button>
                </div>
            `;
            return;
        }

        cotizaciones.forEach(cotizacion => {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg p-6 shadow-sm border table-animate desktop-hover';
            
            card.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-900">#${cotizacion.id}</h3>
                    ${this.renderEstado(cotizacion.estado)}
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                    <div>
                        <span class="text-gray-600 block mb-1">Cliente:</span>
                        <div class="font-medium text-gray-900">${cotizacion.cliente}</div>
                    </div>
                    <div>
                        <span class="text-gray-600 block mb-1">Vendedor:</span>
                        <div class="text-gray-900">${cotizacion.vendedor}</div>
                    </div>
                    <div>
                        <span class="text-gray-600 block mb-1">Fecha:</span>
                        <div class="text-gray-900">${cotizacion.fechaFormateada}</div>
                    </div>
                    <div>
                        <span class="text-gray-600 block mb-1">Total:</span>
                        <div class="font-semibold text-lg text-gray-900">${cotizacion.totalFormateado}</div>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    ${this.renderAcciones(cotizacion)}
                </div>
            `;
            
            container.appendChild(card);
        });
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

    renderAcciones(cotizacion) {
        const isAdmin = window.CURRENT_USER.role === 'admin';
        const isOwner = cotizacion.vendedorId === window.CURRENT_USER.id;
        
        let acciones = `
            <button onclick="gestorCotizaciones.verDetalle(${cotizacion.id})" 
                    class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium flex items-center justify-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Ver
            </button>
        `;
        
        // Botón QR para cotizaciones completadas e impresas
        if (cotizacion.estado === 'completada' || cotizacion.estado === 'impresa') {
            acciones += `
                <button onclick="gestorCotizaciones.mostrarModalQR(${cotizacion.id})" 
                        class="flex-1 bg-purple-600 text-white px-3 py-2 rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11a2 2 0 01-2 2H8a2 2 0 01-2-2V9a2 2 0 012-2h8a2 2 0 012 2v6zM8 9l8 8M16 9l-8 8"></path>
                    </svg>
                    QR
                </button>
                ${window.EMAIL_ENABLED ? `
                <button onclick="gestorCotizaciones.mostrarModalEmail(${cotizacion.id})"
                        class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email
                </button>
                ` : ''}
            `;
        }
        
        if (cotizacion.estado !== 'borrador') {
            // acciones += `
            //     <button onclick="gestorCotizaciones.imprimir(${cotizacion.id})" 
            //             class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
            //         Imprimir
            //     </button>
            // `;
        }
        
        if (isAdmin) {
            acciones += `
                <button onclick="gestorCotizaciones.eliminar(${cotizacion.id})" 
                        class="flex-1 bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Eliminar
                </button>
            `;
        }
        
        return acciones;
    }

    renderAccionesDesktop(cotizacion) {
        const isAdmin = window.CURRENT_USER.role === 'admin';
        const isOwner = cotizacion.vendedorId === window.CURRENT_USER.id;
        
        let acciones = `
            <button onclick="gestorCotizaciones.verDetalle(${cotizacion.id})" 
                    class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700 transition-colors" title="Ver detalle">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            </button>
        `;
        
        // Botón QR para cotizaciones completadas e impresas
        if (cotizacion.estado === 'completada' || cotizacion.estado === 'impresa') {
            acciones += `
                <button onclick="gestorCotizaciones.mostrarModalQR(${cotizacion.id})" 
                        class="bg-purple-600 text-white px-2 py-1 rounded text-xs hover:bg-purple-700 transition-colors" title="Código QR">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11a2 2 0 01-2 2H8a2 2 0 01-2-2V9a2 2 0 012-2h8a2 2 0 012 2v6zM8 9l8 8M16 9l-8 8"></path>
                    </svg>
                </button>
                <button onclick="gestorCotizaciones.mostrarModalEmail(${cotizacion.id})" 
                        class="bg-green-600 text-white px-2 py-1 rounded text-xs hover:bg-green-700 transition-colors" title="Enviar por email">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </button>
            `;
        }
        
        if (isAdmin) {
            acciones += `
                <button onclick="gestorCotizaciones.eliminar(${cotizacion.id})" 
                        class="bg-red-600 text-white px-2 py-1 rounded text-xs hover:bg-red-700 transition-colors" title="Eliminar">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            `;
        }
        
        return acciones;
    }

    actualizarPaginacion(pagination) {
        document.getElementById('desde').textContent = pagination.from;
        document.getElementById('hasta').textContent = pagination.to;
        document.getElementById('total').textContent = pagination.total_records;

        // Botones anterior/siguiente
        document.getElementById('pagina-anterior').disabled = !pagination.has_prev;
        document.getElementById('pagina-siguiente').disabled = !pagination.has_next;

        // Números de página
        const contenedorNumeros = document.getElementById('numeros-pagina');
        contenedorNumeros.innerHTML = '';

        const maxPaginas = 5;
        const mitad = Math.floor(maxPaginas / 2);
        let inicio = Math.max(1, pagination.current_page - mitad);
        let fin = Math.min(pagination.total_pages, inicio + maxPaginas - 1);
        
        if (fin - inicio + 1 < maxPaginas) {
            inicio = Math.max(1, fin - maxPaginas + 1);
        }

        for (let i = inicio; i <= fin; i++) {
            const btn = document.createElement('button');
            btn.className = `px-3 py-2 text-sm border rounded-lg ${
                i === pagination.current_page 
                    ? 'bg-accent text-white border-accent' 
                    : 'border-gray-300 hover:bg-gray-50'
            }`;
            btn.textContent = i;
            btn.addEventListener('click', () => this.irAPagina(i));
            contenedorNumeros.appendChild(btn);
        }
    }

    actualizarContador(total) {
        document.getElementById('total-registros').textContent = total;
    }

    mostrarLoading(mostrar) {
        const loading = document.getElementById('loading-state');
        const mainContent = document.getElementById('main-content');
        
        if (mostrar) {
            loading.classList.remove('hidden');
            loading.classList.add('flex');
            mainContent.classList.add('hidden');
        } else {
            loading.classList.add('hidden');
            loading.classList.remove('flex');
            mainContent.classList.remove('hidden');
        }
    }

    mostrarError(mensaje) {
        console.error('Error en cotizaciones:', mensaje);
        
        // Mostrar el error en ambas vistas
        this.mostrarMensajeEnVistas(mensaje, 'error');
        
        // También mostrar alerta para casos críticos
        if (mensaje.includes('autorizado') || mensaje.includes('autenticación')) {
            alert('Sesión expirada. Por favor, inicie sesión nuevamente.');
            // Opcional: redirigir al login
            // window.location.href = 'login.php';
        } else {
            alert('Error: ' + mensaje);
        }
    }

    mostrarExito(mensaje) {
        const toast = document.createElement('div');
        toast.className = 'fixed top-5 right-5 bg-green-500 text-white py-2 px-4 rounded-lg shadow-lg z-50';
        toast.textContent = mensaje;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    mostrarMensajeEnVistas(mensaje, tipo = 'info') {
        const icono = tipo === 'error' ? '❌' : tipo === 'warning' ? '⚠️' : 'ℹ️';
        const colorClass = tipo === 'error' ? 'text-red-500' : tipo === 'warning' ? 'text-yellow-500' : 'text-blue-500';
        
        const mensajeHtml = `
            <div class="text-center py-8 ${colorClass}">
                <div class="text-4xl mb-3">${icono}</div>
                <div class="font-medium mb-2">${tipo === 'error' ? 'Error al cargar cotizaciones' : 'Información'}</div>
                <div class="text-sm text-gray-600 mb-4">${mensaje}</div>
                <button onclick="window.location.reload()" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600">
                    🔄 Reintentar
                </button>
                <div class="text-xs text-gray-400 mt-2">
                    Si el problema persiste, contacte al administrador
                </div>
            </div>
        `;
        
        // Actualizar vista tabla (mobile)
        const containerMobile = document.getElementById('lista-cotizaciones-mobile');
        if (containerMobile) {
            containerMobile.innerHTML = mensajeHtml;
        }
        
        // Actualizar vista cards (mobile)
        const containerCards = document.getElementById('lista-cotizaciones-cards');
        if (containerCards) {
            containerCards.innerHTML = mensajeHtml;
        }
        
        // Actualizar vista tabla (desktop)
        const containerDesktopTabla = document.getElementById('lista-cotizaciones-desktop');
        if (containerDesktopTabla) {
            containerDesktopTabla.innerHTML = `<tr><td colspan="6" class="px-4 py-8">${mensajeHtml}</td></tr>`;
        }
        
        // Actualizar vista cards (desktop)
        const containerDesktopCards = document.getElementById('lista-cotizaciones-cards-desktop');
        if (containerDesktopCards) {
            containerDesktopCards.innerHTML = `<div class="col-span-full">${mensajeHtml}</div>`;
        }
    }

    // Acciones de cotizaciones
    verDetalle(id) {
        window.location.href = `detalle-cotizacion.php?id=${id}`;
    }

    imprimir(id) {
        window.open(`api/imprimir_cotizacion.php?id=${id}`, '_blank');
    }

    duplicar(id) {
        if (confirm('¿Estás seguro de que quieres duplicar esta cotización?')) {
            // Implementar duplicación
            console.log('Duplicar cotización:', id);
        }
    }

    eliminar(id) {
        if (confirm('¿Estás seguro de que quieres eliminar esta cotización? Esta acción no se puede deshacer.')) {
            this.eliminarCotizacion(id);
        }
    }

    async eliminarCotizacion(id) {
        try {
            const respuesta = await fetch(`api/eliminar_cotizacion.php?id=${id}`, {
                method: 'DELETE',
                credentials: 'same-origin'
            });
            
            const datos = await respuesta.json();
            
            if (datos.status === 'success') {
                this.cargarCotizaciones();
            } else {
                this.mostrarError(datos.message);
            }
        } catch (error) {
            console.error('Error eliminando cotización:', error);
            this.mostrarError('Error al eliminar la cotización');
        }
    }
    
    // Métodos para enlaces compartidos y QR
    async mostrarModalQR(cotizacionId) {
        try {
            // Primero intentar generar el enlace compartido
            const response = await fetch('api/generar_enlace_compartido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    cotizacion_id: cotizacionId
                })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                const enlaceData = data.data;
                enlaceData.cotizacion_id = cotizacionId;
                this.crearModalQR(enlaceData);
            } else {
                this.mostrarError(data.message || 'Error al generar enlace compartido');
            }
        } catch (error) {
            console.error('Error al generar enlace compartido:', error);
            this.mostrarError('Error de conexión al generar enlace');
        }
    }
    
    async mostrarModalEmail(cotizacionId) {
        this.crearModalEmail(cotizacionId);
    }
    
    crearModalQR(enlaceData) {
        // Crear modal si no existe
        let modal = document.getElementById('modal-qr');
        if (modal) {
            modal.remove();
        }
        
        modal = document.createElement('div');
        modal.id = 'modal-qr';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        
        modal.innerHTML = `
            <div class="bg-white rounded-lg max-w-sm w-full p-6 relative">
                <button id="cerrar-modal-qr" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Compartir Cotización</h3>
                    
                    <div class="mb-4">
                        <div class="bg-gray-100 p-4 rounded-lg inline-block">
                            <img src="${enlaceData.qr_url}" alt="Código QR" class="w-48 h-48 mx-auto">
                        </div>
                        <p class="text-sm text-gray-600 mt-2">Folio: <span class="font-mono font-semibold">${enlaceData.folio}</span></p>
                        <p class="text-xs text-gray-500">Cliente: ${enlaceData.cliente}</p>
                        <p class="text-xs text-red-500 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Válido por 24 horas
                        </p>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded border">
                            <div class="flex items-center">
                                <input type="text" id="enlace-publico-modal" readonly 
                                       value="${enlaceData.url_publica}" 
                                       class="flex-1 text-xs text-gray-600 bg-transparent border-none outline-none mr-2">
                                <button id="btn-copiar-enlace-modal" 
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium px-2 py-1">
                                    Copiar
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            ${window.CURRENT_USER.role === 'admin' ? `
                                <button id="btn-gestionar-enlace" 
                                        class="flex-1 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium">
                                    Gestionar
                                </button>
                            ` : ''}
                            <button id="btn-cerrar-modal" 
                                    class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm font-medium">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Event listeners
        const cerrarModal = () => {
            modal.remove();
        };
        
        document.getElementById('cerrar-modal-qr').addEventListener('click', cerrarModal);
        document.getElementById('btn-cerrar-modal').addEventListener('click', cerrarModal);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModal();
        });
        
        // Copiar enlace
        document.getElementById('btn-copiar-enlace-modal').addEventListener('click', async () => {
            const enlace = document.getElementById('enlace-publico-modal').value;
            
            try {
                await navigator.clipboard.writeText(enlace);
                
                const btn = document.getElementById('btn-copiar-enlace-modal');
                const textoOriginal = btn.textContent;
                btn.textContent = '¡Copiado!';
                btn.className = btn.className.replace('text-blue-600', 'text-green-600');
                
                setTimeout(() => {
                    btn.textContent = textoOriginal;
                    btn.className = btn.className.replace('text-green-600', 'text-blue-600');
                }, 2000);
                
            } catch (error) {
                console.error('Error al copiar enlace:', error);
                const enlaceInput = document.getElementById('enlace-publico-modal');
                enlaceInput.select();
                document.execCommand('copy');
                this.mostrarExito('Enlace copiado al portapapeles');
            }
        });
        
        // Gestionar enlace (solo admin)
        const btnGestionar = document.getElementById('btn-gestionar-enlace');
        if (btnGestionar) {
            btnGestionar.addEventListener('click', () => {
                cerrarModal();
                this.mostrarPanelGestion(enlaceData);
            });
        }
    }
    
    crearModalEmail(cotizacionId) {
        // Crear modal si no existe
        let modal = document.getElementById('modal-email');
        if (modal) {
            modal.remove();
        }
        
        modal = document.createElement('div');
        modal.id = 'modal-email';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        
        // Determinar configuración según el rol
        const userRole = window.CURRENT_USER?.role;
        const userEmail = window.CURRENT_USER?.email;
        const esCliente = userRole === 'client' || userRole === 'cliente';
        
        modal.innerHTML = `
            <div class="bg-white rounded-lg max-w-md w-full p-6 relative">
                <button id="cerrar-modal-email" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Enviar Cotización por Email</h3>
                    <p class="text-sm text-gray-600 mt-2">Envía esta cotización directamente al cliente</p>
                </div>
                
                <!-- Campo email para admin/vendor -->
                ${!esCliente ? `
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email de destino</label>
                    <input type="email" id="email-destino-modal" placeholder="cliente@ejemplo.com" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Ingresa el email del cliente</p>
                </div>
                ` : `
                <div class="mb-4">
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <p class="text-sm text-green-800">
                            <span class="font-medium">Destino:</span> ${userEmail}
                        </p>
                        <p class="text-xs text-green-600 mt-1">Se enviará a tu email registrado</p>
                    </div>
                </div>
                `}
                
                <div class="flex gap-3">
                    <button id="btn-cancelar-email" class="flex-1 bg-gray-100 text-gray-700 py-3 px-4 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancelar
                    </button>
                    <button id="btn-enviar-email-modal" class="flex-1 bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition-colors">
                        <span class="loading-text flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Enviar Email
                        </span>
                        <span class="loading-spinner hidden">
                            <svg class="animate-spin h-5 w-5 text-white inline-block" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle>
                                <path fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" class="opacity-75"></path>
                            </svg>
                            Enviando...
                        </span>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Event listeners
        const cerrarModal = () => {
            modal.remove();
        };
        
        document.getElementById('cerrar-modal-email').addEventListener('click', cerrarModal);
        document.getElementById('btn-cancelar-email').addEventListener('click', cerrarModal);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModal();
        });
        
        // Enviar email
        document.getElementById('btn-enviar-email-modal').addEventListener('click', async () => {
            await this.procesarEnvioEmail(cotizacionId, cerrarModal);
        });
    }
    
    async procesarEnvioEmail(cotizacionId, cerrarModal) {
        const userRole = window.CURRENT_USER?.role;
        const esCliente = userRole === 'client' || userRole === 'cliente';
        let emailDestino = null;
        
        // Validar email según el rol
        if (esCliente) {
            emailDestino = window.CURRENT_USER?.email;
            if (!emailDestino) {
                this.mostrarError('No se encontró tu email registrado');
                return;
            }
        } else {
            // Para admin/vendor, obtener email del campo
            const inputEmail = document.getElementById('email-destino-modal');
            emailDestino = inputEmail?.value?.trim();
            
            if (!emailDestino) {
                this.mostrarError('Por favor ingresa un email de destino');
                return;
            }
            
            if (!this.validarEmail(emailDestino)) {
                this.mostrarError('El formato del email no es válido');
                return;
            }
        }
        
        // Mostrar loading
        const btn = document.getElementById('btn-enviar-email-modal');
        const loadingText = btn.querySelector('.loading-text');
        const loadingSpinner = btn.querySelector('.loading-spinner');
        
        btn.disabled = true;
        loadingText.classList.add('hidden');
        loadingSpinner.classList.remove('hidden');
        
        try {
            const response = await fetch('api/enviar_cotizacion_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    cotizacion_id: cotizacionId,
                    email_destino: emailDestino
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.mostrarExito(`Email enviado exitosamente a ${emailDestino}`);
                cerrarModal();
            } else {
                this.mostrarError(data.message || 'Error al enviar email');
            }
            
        } catch (error) {
            console.error('Error al enviar email:', error);
            let errorMessage = 'Error al enviar email';
            
            if (error.message.includes('HTTP error')) {
                errorMessage = `Error del servidor: ${error.message}`;
            } else if (error.name === 'SyntaxError') {
                errorMessage = 'Error en la respuesta del servidor';
            } else {
                errorMessage = error.message || 'Error de conexión al enviar email';
            }
            
            this.mostrarError(errorMessage);
        } finally {
            // Restaurar botón
            btn.disabled = false;
            loadingText.classList.remove('hidden');
            loadingSpinner.classList.add('hidden');
        }
    }
    
    validarEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    mostrarPanelGestion(enlaceData) {
        // Crear modal de gestión
        let modal = document.getElementById('modal-gestion');
        if (modal) {
            modal.remove();
        }
        
        modal = document.createElement('div');
        modal.id = 'modal-gestion';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        
        modal.innerHTML = `
            <div class="bg-white rounded-lg max-w-md w-full p-6 relative">
                <button id="cerrar-modal-gestion" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                
                <div class="text-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Gestionar Enlace</h3>
                    <p class="text-sm text-gray-600">Folio: <span class="font-mono font-semibold">${enlaceData.folio}</span></p>
                    <p class="text-xs text-gray-500">${enlaceData.cliente}</p>
                </div>
                
                <div class="flex flex-col space-y-3">
                    <button id="btn-reactivar" class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-700 hover:bg-blue-800 transition-colors">
                        Reactivar Enlace
                    </button>
                    <button id="btn-desactivar" class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                        Desactivar Temporalmente
                    </button>
                    <button id="btn-extender" class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-500 hover:bg-blue-600 transition-colors">
                        Extender 24 Horas
                    </button>
                    <button id="btn-regenerar" class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-400 hover:bg-blue-500 transition-colors">
                        Regenerar Folio y QR
                    </button>
                    
                    <hr class="border-t border-gray-200 my-2"/>

                    <button id="btn-deshabilitar" class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 transition-colors">
                        Deshabilitar Permanentemente
                    </button>
                    
                    <button id="btn-cancelar-gestion" class="w-full px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-500 hover:bg-gray-600 transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Event listeners
        const cerrarModal = () => modal.remove();
        
        document.getElementById('cerrar-modal-gestion').addEventListener('click', cerrarModal);
        document.getElementById('btn-cancelar-gestion').addEventListener('click', cerrarModal);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModal();
        });
        
        // Acciones administrativas
        document.getElementById('btn-reactivar').addEventListener('click', async () => {
            await this.ejecutarAccionAdmin(enlaceData, 'activar');
            cerrarModal();
        });
        
        document.getElementById('btn-desactivar').addEventListener('click', async () => {
            await this.ejecutarAccionAdmin(enlaceData, 'desactivar');
            cerrarModal();
        });
        
        document.getElementById('btn-extender').addEventListener('click', async () => {
            await this.ejecutarAccionAdmin(enlaceData, 'extender_expiracion', { horas: 24 });
            cerrarModal();
        });
        
        document.getElementById('btn-regenerar').addEventListener('click', async () => {
            if (confirm('¿Regenerar el folio y QR? El enlace anterior dejará de funcionar.')) {
                await this.regenerarEnlace(enlaceData);
                cerrarModal();
            }
        });
        
        document.getElementById('btn-deshabilitar').addEventListener('click', async () => {
            if (confirm('¿Deshabilitar permanentemente este enlace? Esta acción no se puede deshacer.')) {
                await this.ejecutarAccionAdmin(enlaceData, 'deshabilitar_permanente');
                cerrarModal();
            }
        });
    }
    
    async ejecutarAccionAdmin(enlaceData, accion, parametros = {}) {
        try {
            const response = await fetch('api/gestionar_enlace_compartido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin', // Incluir cookies de sesión
                body: JSON.stringify({
                    cotizacion_id: enlaceData.cotizacion_id || enlaceData.id,
                    accion: accion,
                    parametros: parametros
                })
            });

            if (!response.ok) {
                // Try to get error message from body, then fallback to status text
                let errorMessage = `Error HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    // Ignore if body is not json
                }
                throw new Error(errorMessage);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.mostrarExito(`Acción "${accion}" ejecutada correctamente`);
            } else {
                this.mostrarError(data.message || 'Error al ejecutar la acción');
            }
        } catch (error) {
            console.error('Error en acción administrativa:', error);
            this.mostrarError(error.message || 'Error de conexión al ejecutar la acción');
        }
    }
    
    async regenerarEnlace(enlaceData) {
        try {
            const response = await fetch('api/generar_enlace_compartido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin', // Incluir cookies de sesión
                body: JSON.stringify({
                    cotizacion_id: enlaceData.cotizacion_id || enlaceData.id,
                    regenerar: true
                })
            });

            if (!response.ok) {
                let errorMessage = `Error HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    // Ignore if body is not json
                }
                throw new Error(errorMessage);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.mostrarExito('Enlace regenerado exitosamente');
                // Mostrar nuevo QR
                setTimeout(() => {
                    const newEnlaceData = data.data;
                    newEnlaceData.cotizacion_id = enlaceData.cotizacion_id || enlaceData.id;
                    this.crearModalQR(newEnlaceData);
                }, 1000);
            } else {
                this.mostrarError(data.message || 'Error al regenerar enlace');
            }
        } catch (error) {
            console.error('Error al regenerar enlace:', error);
            this.mostrarError(error.message || 'Error de conexión al regenerar enlace');
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.gestorCotizaciones = new GestorCotizaciones();
});