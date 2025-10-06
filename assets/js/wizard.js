// Controlador del Asistente - Lógica principal de la aplicación
class ControladorAsistente {
    constructor() {
        this.faseActual = 0;
        this.totalFases = 5;
        this.datosCotizacion = {
            userId: window.CURRENT_USER ? window.CURRENT_USER.id : null,
            tipoCliente: '',
            tipoClienteDescripcion: '',
            nombreCliente: '',
            tasa: 0,
            comision: 0,
            moneda: 'MXN',
            plazoGlobal: 0,
            residualGlobal: 20,
            anticipo: 0,
            equipos: [],
            totales: {
                contrato: 0,
                utilidad: 0
            },
            tasaPersonalizada: false
        };
        this.idCotizacionActual = null;
        this.temporizadorAutoguardado = null;
        
        this.inicializar();
    }

    inicializar() {
        this.vincularEventos();
        this.cargarCatalogos();
        this.configurarInterfazSegunRol();
        this.mostrarFase(0);
        this.configurarAutoguardado();
    }

    vincularEventos() {
        // Botones de navegación
        document.getElementById('boton-siguiente').addEventListener('click', () => this.siguienteFase());
        document.getElementById('boton-anterior').addEventListener('click', () => this.faseAnterior());
        document.getElementById('boton-atras').addEventListener('click', () => this.faseAnterior());
        
        // Fase 0 - Inicio
        document.getElementById('boton-nueva-cotizacion').addEventListener('click', () => this.iniciarNuevaCotizacion());
        
        // Fase 5 - Confirmación final
        // document.getElementById('imprimir-cotizacion').addEventListener('click', () => this.imprimirCotizacion());
        document.getElementById('nueva-cotizacion-final').addEventListener('click', () => this.nuevaCotizacion());
        
        // Fase 1 - Cliente y Tasa
        document.getElementById('tipo-cliente').addEventListener('change', (e) => this.manejarCambioTipoCliente(e));
        document.getElementById('nombre-cliente').addEventListener('input', (e) => this.manejarCambioNombreCliente(e));
        
        // Fase 2 - Términos Globales
        document.getElementById('plazo-global').addEventListener('change', (e) => this.manejarCambioPlazoGlobal(e));
        document.getElementById('plazo-personalizado-global').addEventListener('blur', (e) => this.manejarCambioPlazoPersonalizado(e));
        document.getElementById('plazo-personalizado-global').addEventListener('input', (e) => this.manejarCambioPlazoPersonalizadoSinValidacion(e));
        document.getElementById('residual-global').addEventListener('blur', (e) => this.manejarCambioResidualGlobal(e));
        document.getElementById('residual-global').addEventListener('input', (e) => this.manejarCambioResidualSinValidacion(e));

        // Anticipo
        document.getElementById('anticipo-si').addEventListener('change', (e) => this.manejarCambioAnticipo(e));
        document.getElementById('anticipo-no').addEventListener('change', (e) => this.manejarCambioAnticipo(e));
        document.getElementById('monto-anticipo').addEventListener('input', (e) => this.manejarCambioMontoAnticipo(e));
        document.getElementById('monto-anticipo').addEventListener('blur', (e) => this.validarMontoAnticipo(e));

        // Fase 3 - Equipos
        document.getElementById('agregar-equipo').addEventListener('click', () => this.agregarEquipo());
        // document.getElementById('agregar-otro').addEventListener('click', () => this.agregarOtroEquipo());
        
        // Manejadores para campos personalizados
        document.getElementById('tipo-equipo').addEventListener('change', (e) => this.manejarCambioTipoEquipo(e));
        document.getElementById('marca-equipo').addEventListener('change', (e) => this.manejarCambioMarcaEquipo(e));
        
        this.configurarValidacionFormularios();
    }

    configurarValidacionFormularios() {
        const camposRequeridos = ['tipo-cliente', 'nombre-cliente', 'cantidad-equipo', 'tipo-equipo', 'marca-equipo', 'costo-equipo'];
        
        camposRequeridos.forEach(idCampo => {
            const campo = document.getElementById(idCampo);
            if (campo) {
                campo.addEventListener('blur', () => this.validarCampo(idCampo));
                campo.addEventListener('input', () => this.limpiarErrorCampo(idCampo));
                
                if (['costo-equipo', 'cantidad-equipo'].includes(idCampo)) {
                    campo.addEventListener('input', () => {
                        if (window.controladorCalculos) {
                            window.controladorCalculos.actualizarCalculosEnVivo();
                        }
                    });
                }
            }
        });
    }

    async cargarCatalogos() {
        try {
            this.mostrarCargando(true);
            
            const respuesta = await fetch('api/get_catalogos.php');
            const datos = await respuesta.json();
            
            if (datos.status === 'success') {
                // Guardar datos globalmente para acceso posterior
                window.CATALOGOS_DATA = datos.data;
                
                this.catalogoTiposCliente(datos.data.clientTypes);
                this.catalogoTiposEquipo(datos.data.equipment);
                this.catalogoMarcas(datos.data.brands);
                this.catalogoPlazos(datos.data.terms);
                this.catalogoMonedas(datos.data.currencies);
                this.catalogoModelos(datos.data.models);
            } else {
                this.mostrarError('Error al cargar los catálogos: ' + datos.message);
            }
        } catch (error) {
            this.mostrarError('Error de conexión al cargar catálogos');
            console.error('Error cargando catálogos:', error);
        } finally {
            this.mostrarCargando(false);
        }
    }

    catalogoTiposCliente(tiposCliente) {
        const select = document.getElementById('tipo-cliente');
        select.innerHTML = '<option value="">Selecciona tipo de cliente</option>';
        
        tiposCliente.forEach(tipo => {
            console.log(tipo)

            const option = document.createElement('option');
            option.value = tipo.codigo;
            option.textContent = `${tipo.descripcion}`;
            option.dataset.rate = tipo.tasa;
            option.dataset.commission = tipo.comision;
            select.appendChild(option);
        });
    }

    catalogoTiposEquipo(equipos) {
        const select = document.getElementById('tipo-equipo');
        select.innerHTML = '<option value="">Seleccionar equipo</option>';
        
        equipos.forEach(equipo => {
            const option = document.createElement('option');
            option.value = equipo.nombre;
            option.textContent = equipo.nombre;
            option.dataset.insurance = equipo.tarifaSeguro;
            option.dataset.incluirPlacas = equipo.incluirPlacas ? '1' : '0';
            option.dataset.incluirGps = equipo.incluirGPS ? '1' : '0';
            select.appendChild(option);
        });
        
        select.addEventListener('change', (e) => this.manejarCambioTipoEquipo(e));
    }

    catalogoMarcas(marcas) {
        const select = document.getElementById('marca-equipo');
        select.innerHTML = '<option value="">Seleccionar marca</option>';
        
        marcas.forEach(marca => {
            const option = document.createElement('option');
            option.value = marca.nombre;
            option.textContent = marca.nombre;
            select.appendChild(option);
        });
    }

    catalogoPlazos(plazos) {
        this.todosLosPlazos = plazos;
        
        const select = document.getElementById('plazo-equipo');
        if (select) {
            select.innerHTML = '<option value="">Seleccionar</option>';
            
            plazos.forEach(plazo => {
                const option = document.createElement('option');
                option.value = plazo.valor;
                option.textContent = plazo.descripcion;
                if (plazo.meses) {
                    option.dataset.months = plazo.meses;
                }
                select.appendChild(option);
            });
            
            select.addEventListener('change', (e) => this.manejarCambioTermino(e));
        }
    }

    catalogoMonedas(monedas) {
        const select = document.getElementById('tipo-moneda');
        select.innerHTML = '<option value="">Selecciona moneda</option>';
        
        monedas.forEach(moneda => {
            const option = document.createElement('option');
            option.value = moneda.codigo;
            option.textContent = `${moneda.simbolo} ${moneda.descripcion}`;
            select.appendChild(option);
        });
        
        select.value = 'MXN';
        this.datosCotizacion.moneda = 'MXN';
        
        select.addEventListener('change', (e) => this.manejarCambioMoneda(e));
    }

    catalogoModelos(modelos) {
        this.todosLosModelos = modelos;
    }

    cargarCatalogoPlazos() {
        const selectGlobal = document.getElementById('plazo-global');
        if (selectGlobal && this.todosLosPlazos) {
            selectGlobal.innerHTML = '<option value="">Seleccionar plazo</option>';
            
            this.todosLosPlazos.forEach(plazo => {
                const option = document.createElement('option');
                option.value = plazo.valor;
                option.textContent = plazo.descripcion;
                if (plazo.meses) {
                    option.dataset.months = plazo.meses;
                }
                selectGlobal.appendChild(option);
            });
        }
    }

    manejarCambioTipoCliente(e) {
        const opcionSeleccionada = e.target.selectedOptions[0];
        const contenedorTasaPersonalizada = document.getElementById('contenedor-tasa-personalizada');
        const inputTasaPersonalizada = document.getElementById('tasa-personalizada');

        if (opcionSeleccionada && opcionSeleccionada.dataset.rate) {
            this.datosCotizacion.tipoCliente = e.target.value;
            this.datosCotizacion.tipoClienteDescripcion = opcionSeleccionada.textContent; // Guardar descripción completa
            this.datosCotizacion.comision = parseFloat(opcionSeleccionada.dataset.commission);

            // Establecer tasa del catálogo
            this.datosCotizacion.tasa = parseFloat(opcionSeleccionada.dataset.rate) / 12;

            // Verificar si el usuario es admin o vendedor
            const esAdminOVendedor = window.CURRENT_USER && (window.CURRENT_USER.role === 'admin' || window.CURRENT_USER.role === 'vendor');

            if (esAdminOVendedor) {
                // Mostrar campo de tasa personalizada para admin/vendedor en TODOS los tipos de cliente
                contenedorTasaPersonalizada.classList.remove('hidden');

                // Establecer valor por defecto (tasa del catálogo en formato anual)
                const tasaAnualDefault = parseFloat(opcionSeleccionada.dataset.rate) * 100;
                inputTasaPersonalizada.value = tasaAnualDefault.toFixed(1);
                this.datosCotizacion.tasaPersonalizada = true;

                // Event listener para cambios en tasa personalizada (actualizar en tiempo real)
                inputTasaPersonalizada.removeEventListener('input', this.handleTasaPersonalizadaInput);
                this.handleTasaPersonalizadaInput = (event) => {
                    let tasaAnual = parseFloat(event.target.value);

                    // Si el valor es válido, actualizar cálculos
                    if (!isNaN(tasaAnual) && tasaAnual > 0) {
                        // Convertir tasa anual a mensual para cálculos internos
                        this.datosCotizacion.tasa = (tasaAnual / 100) / 12;

                        // Actualizar visualización en panel de configuración financiera
                        const tasaElemento = document.getElementById('tasa-actual');
                        if (tasaElemento) {
                            tasaElemento.textContent = `${tasaAnual.toFixed(2)}%`;
                        }

                        // Actualizar footer (información destacada)
                        this.actualizarFooterInfo();
                    }
                };
                inputTasaPersonalizada.addEventListener('input', this.handleTasaPersonalizadaInput);

                // Event listener para validar rango cuando pierde el foco
                inputTasaPersonalizada.removeEventListener('blur', this.handleTasaPersonalizadaBlur);
                this.handleTasaPersonalizadaBlur = (event) => {
                    let tasaAnual = parseFloat(event.target.value);

                    // Si el valor está vacío o es inválido, poner el mínimo
                    if (isNaN(tasaAnual) || tasaAnual <= 0) {
                        tasaAnual = 12;
                        event.target.value = 12;
                    }
                    // Validar rango 12-24%
                    else if (tasaAnual < 12) {
                        tasaAnual = 12;
                        event.target.value = 12;
                    } else if (tasaAnual > 24) {
                        tasaAnual = 24;
                        event.target.value = 24;
                    }

                    // Actualizar con el valor validado
                    this.datosCotizacion.tasa = (tasaAnual / 100) / 12;

                    // Actualizar visualización en panel de configuración financiera
                    const tasaElemento = document.getElementById('tasa-actual');
                    if (tasaElemento) {
                        tasaElemento.textContent = `${tasaAnual.toFixed(2)}%`;
                    }

                    // Actualizar footer (información destacada)
                    this.actualizarFooterInfo();

                    console.log('Tasa personalizada validada:', {
                        tasaAnual: tasaAnual + '%',
                        tasaMensual: (this.datosCotizacion.tasa * 100).toFixed(4) + '%'
                    });
                };
                inputTasaPersonalizada.addEventListener('blur', this.handleTasaPersonalizadaBlur);

            } else {
                // Ocultar campo de tasa personalizada para clientes
                contenedorTasaPersonalizada.classList.add('hidden');
                this.datosCotizacion.tasaPersonalizada = false;
            }

            // Debug: verificar valores
            console.log('Tipo cliente seleccionado:', {
                tipoCliente: this.datosCotizacion.tipoCliente,
                tasaOriginal: opcionSeleccionada.dataset.rate,
                tasaMensual: this.datosCotizacion.tasa,
                comision: this.datosCotizacion.comision,
                esAdminOVendedor: esAdminOVendedor,
                tasaPersonalizada: this.datosCotizacion.tasaPersonalizada
            });

            // Solo actualizar tasa si el elemento existe (vendedores) - MOSTRAR TASA ANUAL
            const tasaElemento = document.getElementById('tasa-actual');
            if (tasaElemento) {
                const tasaAnual = this.datosCotizacion.tasa * 12 * 100;
                tasaElemento.textContent = `${tasaAnual.toFixed(2)}%`;
            }
            
            // Actualizar cálculos preliminares si ya hay datos de equipo
            if (window.controladorCalculos) {
                window.controladorCalculos.actualizarCalculosEnVivo();
            }
            
            this.actualizarFooterInfo();
            this.validarFase1();
        }
    }

    manejarCambioNombreCliente(e) {
        this.datosCotizacion.nombreCliente = e.target.value.trim();
        this.validarFase1();
    }

    manejarCambioMoneda(e) {
        this.datosCotizacion.moneda = e.target.value;
        this.validarFase1();
    }

    // Manejadores Fase 2
    manejarCambioPlazoGlobal(e) {
        const valorSeleccionado = e.target.value;
        const contenedorPersonalizado = document.getElementById('contenedor-plazo-personalizado');
        
        if (valorSeleccionado === 'OTRO') {
            contenedorPersonalizado.classList.remove('hidden');
            this.datosCotizacion.plazoGlobal = 0;
        } else {
            contenedorPersonalizado.classList.add('hidden');
            const opcion = e.target.selectedOptions[0];
            this.datosCotizacion.plazoGlobal = opcion && opcion.dataset.months ? 
                                           parseInt(opcion.dataset.months) : 
                                           parseInt(valorSeleccionado) || 0;
        }
        this.actualizarTerminosMostrados();
        this.actualizarFooterInfo();
        this.recalcularTodosLosEquipos();
        this.validarFase2();
    }

    manejarCambioPlazoPersonalizadoSinValidacion(e) {
        // Solo actualizar el valor sin validar (para input en tiempo real)
        const valor = parseInt(e.target.value) || 12;
        // No validamos límites aquí para permitir que el usuario escriba
        this.datosCotizacion.plazoGlobal = valor;
        this.actualizarTerminosMostrados();
        this.actualizarFooterInfo();
        this.recalcularTodosLosEquipos();
        this.validarFase2();
    }

    manejarCambioPlazoPersonalizado(e) {
        const valor = parseInt(e.target.value) || 12;
        
        // Validar que el plazo esté dentro del rango permitido (solo en blur)
        if (valor > 36) {
            this.mostrarError('El plazo máximo permitido es 36 meses');
            e.target.value = 36;
            this.datosCotizacion.plazoGlobal = 36;
        } else if (valor < 12 && valor !== 0) {
            this.mostrarError('El plazo mínimo permitido es 12 meses');
            e.target.value = 12;
            this.datosCotizacion.plazoGlobal = 12;
        } else {
            this.datosCotizacion.plazoGlobal = valor;
        }
        
        this.actualizarTerminosMostrados();
        this.actualizarFooterInfo();
        this.recalcularTodosLosEquipos();
        this.validarFase2();
    }

    manejarCambioResidualSinValidacion(e) {
        // Solo actualizar el valor sin validar (para input en tiempo real)
        let valor = parseFloat(e.target.value) || 20;
        // No validamos límites aquí para permitir que el usuario escriba
        this.datosCotizacion.residualGlobal = valor;
        this.actualizarTerminosMostrados();
        this.actualizarFooterInfo();
        this.recalcularTodosLosEquipos();
        this.validarFase2();
    }

    manejarCambioResidualGlobal(e) {
        let valor = parseFloat(e.target.value) || 20;

        // Validar que el valor esté dentro del rango permitido (solo en blur)
        if (valor > 50) {
            this.mostrarError('El porcentaje residual máximo permitido es 50%');
            e.target.value = 50;
            valor = 50;
        } else if (valor < 20) {
            this.mostrarError('El porcentaje residual mínimo permitido es 20%');
            e.target.value = 20;
            valor = 20;
        }

        this.datosCotizacion.residualGlobal = valor;
        this.actualizarTerminosMostrados();
        this.actualizarFooterInfo();
        this.recalcularTodosLosEquipos();
        this.validarFase2();
    }

    manejarCambioAnticipo(e) {
        const contenedorAnticipo = document.getElementById('contenedor-anticipo');
        const montoAnticipoInput = document.getElementById('monto-anticipo');

        if (e.target.value === 'si') {
            // Mostrar input de anticipo
            contenedorAnticipo.classList.remove('hidden');
        } else {
            // Ocultar input y resetear valor
            contenedorAnticipo.classList.add('hidden');
            montoAnticipoInput.value = '';
            this.datosCotizacion.anticipo = 0;
            this.actualizarTotales();
        }
    }

    manejarCambioMontoAnticipo(e) {
        // Actualizar valor en tiempo real
        let monto = parseFloat(e.target.value) || 0;
        this.datosCotizacion.anticipo = monto;
        this.actualizarTotales();
    }

    validarMontoAnticipo(e) {
        let monto = parseFloat(e.target.value) || 0;

        // Validar máximo 200,000
        if (monto > 200000) {
            alert('El anticipo máximo permitido es $200,000.00');
            e.target.value = 200000;
            monto = 200000;
        } else if (monto < 0) {
            e.target.value = 0;
            monto = 0;
        }

        this.datosCotizacion.anticipo = monto;
        this.actualizarTotales();
    }

    actualizarTerminosMostrados() {
        const displayPlazo = document.getElementById('mostrar-plazo');
        const displayResidual = document.getElementById('mostrar-residual');
        
        if (displayPlazo) {
            displayPlazo.textContent = this.datosCotizacion.plazoGlobal > 0 ? 
                                    `${this.datosCotizacion.plazoGlobal} meses` : 
                                    'No definido';
        }
        
        if (displayResidual) {
            displayResidual.textContent = `${this.datosCotizacion.residualGlobal}%`;
        }
    }

    recalcularTodosLosEquipos() {
        this.datosCotizacion.equipos.forEach((equipo, index) => {
            equipo.term = this.datosCotizacion.plazoGlobal;
            equipo.residual = this.datosCotizacion.residualGlobal;

            if (equipo.cost > 0 && this.datosCotizacion.tasa > 0) {
                const tarifaSeguro = this.obtenerTarifaSeguro(equipo.type);

                equipo.calculations = window.controladorCalculos.calcularValoresEquipo(
                    equipo.cost,
                    this.datosCotizacion.tasa,
                    equipo.term,
                    equipo.residual,
                    tarifaSeguro,
                    equipo.quantity,
                    0, // Sin anticipo en recálculo manual
                    equipo.includeGPS,
                    equipo.includePlacas
                );
            }
        });

        this.actualizarTotales();
        this.render();
    }

    manejarCambioTipoEquipo(e) {
        const equipoSeleccionado = e.target.value;
        const contenedorModelo = document.getElementById('contenedor-modelo');
        const selectModelo = document.getElementById('modelo-equipo');
        const contenedorOtroEquipo = document.getElementById('contenedor-otro-equipo');
        const campoOtroEquipo = document.getElementById('otro-equipo');
        
        // Verificar que los elementos existan
        if (!contenedorModelo || !selectModelo) {
            console.warn('⚠️ Elementos de modelo no encontrados:', {
                contenedorModelo: !!contenedorModelo,
                selectModelo: !!selectModelo
            });
            return;
        }
        
        // Manejar campo "Otro equipo"
        if (equipoSeleccionado === 'OTRO') {
            contenedorOtroEquipo.classList.remove('hidden');
            campoOtroEquipo.required = true;
            // Ocultar modelo cuando se selecciona "OTRO"
            contenedorModelo.classList.add('hidden');
            selectModelo.innerHTML = '<option value="">Seleccionar modelo</option>';
        } else {
            contenedorOtroEquipo.classList.add('hidden');
            campoOtroEquipo.required = false;
            campoOtroEquipo.value = ''; // Limpiar el campo
            
            // Manejar modelos para equipos del catálogo
            if (equipoSeleccionado && this.todosLosModelos) {
                const modelosFiltrados = this.todosLosModelos.filter(modelo => modelo.equipo === equipoSeleccionado);
                
                if (modelosFiltrados.length > 0) {
                    contenedorModelo.classList.remove('hidden');
                    
                    selectModelo.innerHTML = '<option value="">Seleccionar modelo</option>';
                    modelosFiltrados.forEach(modelo => {
                        const option = document.createElement('option');
                        option.value = modelo.codigo;
                        option.textContent = modelo.descripcion;
                        selectModelo.appendChild(option);
                    });
                } else {
                    contenedorModelo.classList.add('hidden');
                    selectModelo.innerHTML = '<option value="">Seleccionar modelo</option>';
                }
            } else {
                if (contenedorModelo) contenedorModelo.classList.add('hidden');
                if (selectModelo) selectModelo.innerHTML = '<option value="">Seleccionar modelo</option>';
            }
        }
        
        if (window.controladorCalculos) {
            window.controladorCalculos.actualizarCalculosEnVivo();
        }
    }

    manejarCambioMarcaEquipo(e) {
        const valorSeleccionado = e.target.value;
        const contenedorOtraMarca = document.getElementById('contenedor-otra-marca');
        const campoOtraMarca = document.getElementById('otra-marca');
        
        if (valorSeleccionado === 'OTRO') {
            contenedorOtraMarca.classList.remove('hidden');
            campoOtraMarca.required = true;
        } else {
            contenedorOtraMarca.classList.add('hidden');
            campoOtraMarca.required = false;
            campoOtraMarca.value = ''; // Limpiar el campo
        }
        
        if (window.controladorCalculos) {
            window.controladorCalculos.actualizarCalculosEnVivo();
        }
    }

    manejarCambioTermino(e) {
        const valorSeleccionado = e.target.value;
        const contenedorPersonalizado = document.querySelector('.custom-term-input');
        
        if (valorSeleccionado === 'OTRO') {
            if (!contenedorPersonalizado) {
                const input = document.createElement('input');
                input.type = 'number';
                input.className = 'custom-term-input w-full p-2 border rounded mt-2';
                input.placeholder = 'Plazo en meses';
                input.min = '1';
                input.max = '120';
                input.id = 'custom-term';
                
                e.target.parentNode.appendChild(input);
                
                setTimeout(() => {
                    input.focus();
                    input.addEventListener('input', () => {
                        if (window.controladorCalculos) {
                            window.controladorCalculos.actualizarCalculosEnVivo();
                        }
                    });
                }, 100);
            }
        }
        
        this.actualizarCalculosEnVivo();
    }

    validarFase1() {
        const esValida = this.datosCotizacion.tipoCliente && this.datosCotizacion.nombreCliente && this.datosCotizacion.moneda;
        this.actualizarBotonSiguiente(esValida);
        return esValida;
    }

    validarFase2() {
        const esValida = this.datosCotizacion.plazoGlobal > 0 &&
                       this.datosCotizacion.residualGlobal >= 20 &&
                       this.datosCotizacion.residualGlobal <= 50;

        console.log('🔍 Validación Fase 2:', {
            plazoGlobal: this.datosCotizacion.plazoGlobal,
            residualGlobal: this.datosCotizacion.residualGlobal,
            esValida: esValida,
            condiciones: {
                plazoMayorQue0: this.datosCotizacion.plazoGlobal > 0,
                residualEntre20y50: this.datosCotizacion.residualGlobal >= 20 && this.datosCotizacion.residualGlobal <= 50
            }
        });

        this.actualizarBotonSiguiente(esValida);
        return esValida;
    }

    agregarEquipo() {
        if (this.validarFormularioEquipo()) {
            const equipo = this.obtenerEquipoDelFormulario();
            const calculos = this.calcularValoresEquipo(equipo);
            
            equipo.calculations = calculos;
            this.datosCotizacion.equipos.push(equipo);
            
            this.render();
            this.limpiarFormularioEquipo();
            this.actualizarDrawerCarrito();
            this.actualizarTotales();
        }
    }

    agregarOtroEquipo() {
        this.agregarEquipo();
    }

    obtenerEquipoDelFormulario() {
        const selectModelo = document.getElementById('modelo-equipo');
        const modeloSeleccionado = selectModelo.value;
        const displayModelo = modeloSeleccionado ? selectModelo.selectedOptions[0]?.textContent : '';

        // Obtener valores de tipo de equipo
        const tipoEquipoSelect = document.getElementById('tipo-equipo');
        const tipoEquipoValue = tipoEquipoSelect.value;
        let tipoEquipoDisplay = tipoEquipoValue;
        
        // Si se seleccionó "OTRO", usar el valor del campo personalizado
        if (tipoEquipoValue === 'OTRO') {
            const otroEquipoInput = document.getElementById('otro-equipo');
            tipoEquipoDisplay = otroEquipoInput.value.trim();
        } else if (tipoEquipoValue) {
            tipoEquipoDisplay = tipoEquipoSelect.selectedOptions[0]?.textContent || tipoEquipoValue;
        }

        // Obtener valores de marca
        const marcaSelect = document.getElementById('marca-equipo');
        const marcaValue = marcaSelect.value;
        let marcaDisplay = marcaValue;

        // Si se seleccionó "OTRO", usar el valor del campo personalizado
        if (marcaValue === 'OTRO') {
            const otraMarcaInput = document.getElementById('otra-marca');
            marcaDisplay = otraMarcaInput.value.trim();
        } else if (marcaValue) {
            marcaDisplay = marcaSelect.selectedOptions[0]?.textContent || marcaValue;
        }

        // Obtener configuración de GPS y Placas del tipo de equipo
        const opcionTipoEquipo = tipoEquipoSelect.selectedOptions[0];
        const includeGPS = opcionTipoEquipo ? (opcionTipoEquipo.dataset.incluirGps === '1' || opcionTipoEquipo.dataset.incluirGps === 'true') : true;
        const includePlacas = opcionTipoEquipo ? (opcionTipoEquipo.dataset.incluirPlacas === '1' || opcionTipoEquipo.dataset.incluirPlacas === 'true') : true;

        return {
            id: Date.now(),
            quantity: parseInt(document.getElementById('cantidad-equipo').value),
            term: this.datosCotizacion.plazoGlobal,
            termDisplay: `${this.datosCotizacion.plazoGlobal} meses`,
            type: tipoEquipoValue,
            typeDisplay: tipoEquipoDisplay,
            brand: marcaValue,
            brandDisplay: marcaDisplay,
            cost: parseFloat(document.getElementById('costo-equipo').value),
            residual: this.datosCotizacion.residualGlobal,
            model: modeloSeleccionado,
            modelDisplay: displayModelo,
            currency: this.datosCotizacion.moneda,
            insuranceRate: this.obtenerTarifaSeguro(tipoEquipoValue),
            includeGPS: includeGPS,
            includePlacas: includePlacas
        };
    }

    obtenerTarifaSeguro(tipoEquipo) {
        const opcion = document.querySelector(`#tipo-equipo option[value="${tipoEquipo}"]`);
        return opcion ? parseFloat(opcion.dataset.insurance) : 0.006;
    }
    
    obtenerConfiguracionEquipo(tipoEquipo) {
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
            return costos;
        }
        // Fallback a valores por defecto
        return { PLACAS: 4200, GPS: 3300 };
    }

    calcularValoresEquipo(equipo) {
        // Usar el controlador de cálculos centralizado para mantener consistencia
        if (window.controladorCalculos) {
            const { cost, term, quantity, residual } = equipo;
            const tasa = this.datosCotizacion.tasa;

            const calculos = window.controladorCalculos.calcularValoresEquipo(
                cost, tasa, term, residual, equipo.insuranceRate, quantity
            );
            
            // Agregar el totalAgrupado actualizado que incluye placas y GPS
            calculos.totalAgrupado = this.redondearHaciaAbajo(
                this.redondearHaciaAbajo(calculos.totalMonthlyPaymentWithExtrasCalculated || calculos.totalPaymentWithExtras, 2) * quantity, 2
            );
            
            return calculos;
        } else {
            // Fallback si no está disponible el controlador
            console.warn('⚠️ ControladorCalculos no disponible, usando cálculos internos');
            return this.calcularValoresEquipoInterno(equipo);
        }
    }

    calcularValoresEquipoInterno(equipo) {
        console.log(equipo)

        const { cost, term, quantity, residual } = equipo;
        const tasa = this.datosCotizacion.tasa;
        const comision = this.datosCotizacion.comision || 0;
        const tarifaIva = 0.16;

        // NUEVO SISTEMA: Tasa ya viene mensual desde la selección de tipo cliente
        const tasaMensual = tasa; // Ya está en formato mensual (ej: 0.02 = 2%)
        const margen = 1 + (term * tasaMensual);
        
        // NUEVO SISTEMA: Costo base incluye GPS/Placas según configuración del equipo
        let costoBase = cost;
        
        // Obtener configuración del equipo y costos adicionales
        const equipmentConfig = this.obtenerConfiguracionEquipo(equipo.type);
        const costosAdicionales = this.obtenerCostosAdicionales();
        
        if (equipmentConfig.incluirPlacas && costosAdicionales.PLACAS) {
            costoBase += costosAdicionales.PLACAS;
        }
        if (equipmentConfig.incluirGPS && costosAdicionales.GPS) {
            costoBase += costosAdicionales.GPS;
        }
        
        const costoEquipo = margen * costoBase;
        const seguro = costoBase * equipo.insuranceRate;
        
        const montoResidual = costoEquipo * (residual / 100);
        const pagoMensualEquipo = (costoEquipo - montoResidual) / term;
        
        const comisionTotal = costoEquipo * comision;
        const subtotalMensual = pagoMensualEquipo + seguro;
        const ivaMensual = subtotalMensual * tarifaIva;
        const pagoMensualTotal = subtotalMensual + ivaMensual;
        
        const ivaResidual = montoResidual * tarifaIva;
        const pagoResidual1 = montoResidual + ivaResidual;
        const pagoResidual3 = ((montoResidual + ivaResidual) * 1.1) / 3;
        
        // NUEVO SISTEMA: El subtotal es simplemente equipo + seguro (GPS/Placas ya están incluidos en el costo base)
        const subtotalCompleto = Math.round(subtotalMensual * 100) / 100;
        const ivaCompleto = Math.round(subtotalCompleto * tarifaIva * 100) / 100;
        const pagoMensualTotalConExtras = Math.round((subtotalCompleto + ivaCompleto) * 100) / 100;

        const costoTotalEquipo = costoEquipo * quantity;
        const seguroTotal = seguro * quantity;
        const pagoMensualFinal = pagoMensualTotal * quantity;
        const costoTotal = costoBase * quantity; // Usar costoBase que ya incluye GPS/Placas

        console.log(costoTotal);
        console.log(costoTotalEquipo);
        
        // Porcentaje de utilidad usando la fórmula: 1 - (costoCompra / costoVenta)
        const utilidadEquipo = costoTotalEquipo > 0 ? parseFloat((1 - (costoTotal / costoTotalEquipo)).toFixed(2)) : 0;

        return {
            margin: this.redondearDecimales(margen, 4),
            saleCost: this.redondearDecimales(costoEquipo, 2),
            equipmentPayment: this.redondearDecimales(pagoMensualEquipo, 2),
            insurance: this.redondearDecimales(seguro, 2),
            residualAmount: this.redondearDecimales(montoResidual, 2),
            totalPayment: this.redondearDecimales(pagoMensualTotal, 2),
            
            // Desglose mensual - GPS/Placas ya incluidos en el costo base
            monthlyEquipmentPayment: this.redondearHaciaAbajo(subtotalMensual, 2), // Equipo + seguro (con GPS/Placas incluidos)
            monthlySubtotal: this.redondearHaciaAbajo(subtotalCompleto, 2), // Subtotal completo
            monthlyIVA: this.redondearHaciaAbajo(ivaCompleto, 2), // IVA sobre subtotal
            totalMonthlyPayment: this.redondearHaciaAbajo(pagoMensualTotalConExtras, 2), // Total mensual final
            totalPaymentWithExtras: this.redondearDecimales(pagoMensualTotalConExtras, 2), // Para compatibilidad
            
            totalCommission: this.redondearDecimales(comisionTotal, 2),
            totalCommissionAmount: this.redondearDecimales(comisionTotal, 2),
            residualIVA: this.redondearDecimales(ivaResidual, 2),
            residual1Payment: this.redondearDecimales(pagoResidual1, 2),
            residual3Payments: this.redondearDecimales(pagoResidual3, 2),
            
            totalSaleCost: this.redondearDecimales(costoTotalEquipo, 2),
            totalEquipmentPayment: this.redondearDecimales(pagoMensualEquipo * quantity, 2),
            totalInsurance: this.redondearDecimales(seguroTotal, 2),
            totalMonthlyPaymentFinal: this.redondearHaciaAbajo(pagoMensualFinal, 2),
            totalCost: this.redondearDecimales(costoTotal, 2),
            totalUtility: utilidadEquipo,
            
            quantity: quantity,
            term: term,
            rate: tasa,
            residualPercentage: residual,
            insuranceRate: equipo.insuranceRate,
            
            // Porcentajes de utilidad
            utilityPercentage: utilidadEquipo,
            
            equipmentCost: this.redondearDecimales(costoEquipo, 2),
            totalEquipmentCost: this.redondearDecimales(costoTotalEquipo, 2),
            totalAgrupado: this.redondearHaciaAbajo(this.redondearHaciaAbajo(pagoMensualTotalConExtras, 2) * quantity, 2), // Valor legacy
            totalResidual: this.redondearHaciaAbajo(this.redondearHaciaAbajo(pagoResidual1, 2)  * quantity, 2)
        };
    }

    validarFormularioEquipo() {
        const camposRequeridos = ['cantidad-equipo', 'tipo-equipo', 'marca-equipo', 'costo-equipo'];
        let esValido = true;

        camposRequeridos.forEach(idCampo => {
            if (!this.validarCampo(idCampo)) {
                esValido = false;
            }
        });

        // Validar campos personalizados
        const tipoEquipo = document.getElementById('tipo-equipo').value;
        const marca = document.getElementById('marca-equipo').value;

        // Si se seleccionó "OTRO" para tipo de equipo, validar el campo personalizado
        if (tipoEquipo === 'OTRO') {
            const otroEquipoInput = document.getElementById('otro-equipo');
            const otroEquipoValor = otroEquipoInput.value.trim();
            
            if (!otroEquipoValor) {
                this.mostrarErrorCampo('otro-equipo', 'Este campo es requerido cuando selecciona "OTRO"');
                esValido = false;
            } else if (otroEquipoValor.length > 80) {
                this.mostrarErrorCampo('otro-equipo', 'El texto no puede exceder 80 caracteres');
                esValido = false;
            } else {
                this.limpiarErrorCampo('otro-equipo');
            }
        }

        // Si se seleccionó "OTRO" para marca, validar el campo personalizado
        if (marca === 'OTRO') {
            const otraMarcaInput = document.getElementById('otra-marca');
            const otraMarcaValor = otraMarcaInput.value.trim();
            
            if (!otraMarcaValor) {
                this.mostrarErrorCampo('otra-marca', 'Este campo es requerido cuando selecciona "OTRO"');
                esValido = false;
            } else if (otraMarcaValor.length > 80) {
                this.mostrarErrorCampo('otra-marca', 'El texto no puede exceder 80 caracteres');
                esValido = false;
            } else {
                this.limpiarErrorCampo('otra-marca');
            }
        }

        if (this.datosCotizacion.plazoGlobal <= 0) {
            this.mostrarError('Debe definir los términos globales antes de agregar equipos');
            return false;
        }

        const cantidad = parseInt(document.getElementById('cantidad-equipo').value);
        const costo = parseFloat(document.getElementById('costo-equipo').value);

        if (cantidad < 1 || cantidad > 999) {
            this.mostrarErrorCampo('cantidad-equipo', 'La cantidad debe estar entre 1 y 999');
            esValido = false;
        }
        
        if (costo < 1 || costo > 99999999) {
            this.mostrarErrorCampo('costo-equipo', 'El costo debe estar entre $1 y $99,999,999');
            esValido = false;
        }

        return esValido;
    }

    validarCampo(idCampo) {
        const campo = document.getElementById(idCampo);
        if (!campo) {
            return true;
        }
        const valor = campo.value.trim();
        
        if (!valor) {
            this.mostrarErrorCampo(idCampo, 'Este campo es requerido');
            return false;
        }
        
        this.limpiarErrorCampo(idCampo);
        return true;
    }

    mostrarErrorCampo(idCampo, mensaje) {
        const campo = document.getElementById(idCampo);
        campo.classList.add('form-error');
        
        const errorExistente = campo.parentNode.querySelector('.error-message');
        if (errorExistente) {
            errorExistente.remove();
        }
        
        const mensajeError = document.createElement('div');
        mensajeError.className = 'error-message text-red-500 text-xs mt-1';
        mensajeError.textContent = mensaje;
        campo.parentNode.appendChild(mensajeError);
    }

    limpiarErrorCampo(idCampo) {
        const campo = document.getElementById(idCampo);
        if (campo) {
            campo.classList.remove('form-error');
            const mensajeError = campo.parentNode.querySelector('.error-message');
            if (mensajeError) {
                mensajeError.remove();
            }
        }
    }

    limpiarFormularioEquipo() {
        const campoCantidad = document.getElementById('cantidad-equipo');
        const campoTipo = document.getElementById('tipo-equipo');
        const campoMarca = document.getElementById('marca-equipo');
        const campoCosto = document.getElementById('costo-equipo');
        
        if (campoCantidad) campoCantidad.value = '1';
        if (campoTipo) campoTipo.value = '';
        if (campoMarca) campoMarca.value = '';
        if (campoCosto) campoCosto.value = '';

        const selectModelo = document.getElementById('modelo-equipo');
        if (selectModelo) {
            selectModelo.value = '';
            const contenedorModelo = document.getElementById('contenedor-modelo');
            if (contenedorModelo) {
                contenedorModelo.classList.add('hidden');
            }
        }

        // Limpiar campos personalizados
        const otroEquipoInput = document.getElementById('otro-equipo');
        const otraMarcaInput = document.getElementById('otra-marca');
        const contenedorOtroEquipo = document.getElementById('contenedor-otro-equipo');
        const contenedorOtraMarca = document.getElementById('contenedor-otra-marca');
        
        if (otroEquipoInput) otroEquipoInput.value = '';
        if (otraMarcaInput) otraMarcaInput.value = '';
        if (contenedorOtroEquipo) contenedorOtroEquipo.classList.add('hidden');
        if (contenedorOtraMarca) contenedorOtraMarca.classList.add('hidden');
        
        ['cantidad-equipo', 'tipo-equipo', 'marca-equipo', 'costo-equipo', 'otro-equipo', 'otra-marca'].forEach(idCampo => {
            this.limpiarErrorCampo(idCampo);
        });
    }

    render() {
        const contenedor = document.getElementById('lista-equipos');
        contenedor.innerHTML = '';

        // Si no hay equipos, mostrar el estado vacío
        if (this.datosCotizacion.equipos.length === 0) {
            contenedor.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <p>No hay equipos agregados</p>
                    <p class="text-xs">Use el formulario de la izquierda para agregar equipos</p>
                </div>
            `;
        } else {
            // Renderizar equipos existentes
            this.datosCotizacion.equipos.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'elemento-equipo fade-in';

            // Obtener valores de anticipo y GPS/Placas
            const anticipoProporcional = item.anticipoProporcional || 0;
            const pagoGPSUnidad = item.calculations.pagoGPS || 0;
            const pagoPlacasUnidad = item.calculations.pagoPlacas || 0;
            const pagoMensual = item.calculations.totalPaymentWithExtras || 0;

            div.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900">${item.quantity}x ${item.typeDisplay || item.type}</h4>
                        <p class="text-sm text-gray-600">${item.brandDisplay || item.brand}${item.modelDisplay ? ` - ${item.modelDisplay}` : ''}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-sm text-gray-500">${item.currency} $${item.cost.toLocaleString()}</p>
                            ${anticipoProporcional > 0 ? `<span class="text-xs text-green-600 font-medium">| Anticipo: -$${this.redondearHaciaAbajo(anticipoProporcional, 2).toLocaleString()}</span>` : ''}
                        </div>
                        <p class="text-sm font-medium text-blue-600 mt-1">Pago mensual: $${this.redondearHaciaAbajo(pagoMensual, 2).toLocaleString()}</p>
                    </div>
                    <div class="flex items-center space-x-1">
                        <button onclick="asistente.toggleDetallesEquipo(${index})" class="text-gray-500 hover:text-gray-700 p-1" title="Ver detalles">
                            <svg class="w-4 h-4 transform transition-transform chevron-equipo-${index}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <button onclick="asistente.eliminarEquipo(${index})" class="text-red-500 hover:text-red-700 p-1" title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="text-sm space-y-1 detalles-equipo-${index} hidden">
                    ${anticipoProporcional > 0 ? `
                    <div class="bg-green-50 p-2 rounded mt-2 space-y-1 border border-green-200">
                        <div class="font-medium text-green-800 mb-2">Anticipo proporcional:</div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-700">Costo total equipo:</span>
                            <span>$${((item.cost + (item.calculations.costoGpsAgregado || 0) + (item.calculations.costoPlacasAgregado || 0)) * item.quantity).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between text-xs font-medium border-t border-green-200 pt-1">
                            <span class="text-green-700">Anticipo aplicado:</span>
                            <span class="text-green-700">-$${this.redondearHaciaAbajo(anticipoProporcional, 2).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between text-xs font-semibold border-t border-green-200 pt-1">
                            <span class="text-gray-800">Costo financiado:</span>
                            <span class="text-gray-800">$${this.redondearHaciaAbajo(((item.cost + (item.calculations.costoGpsAgregado || 0) + (item.calculations.costoPlacasAgregado || 0)) * item.quantity) - anticipoProporcional, 2).toLocaleString()}</span>
                        </div>
                    </div>` : ''}
                    <div class="bg-blue-50 p-2 rounded mt-2 space-y-1">
                        <div class="font-medium text-gray-700 mb-2">Información del equipo:</div>
                        <div class="flex justify-between text-xs">
                            <span>Moneda:</span>
                            <span>${item.currency}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>Costo producto:</span>
                            <span>$${item.cost.toLocaleString()}</span>
                        </div>

                        ${window.CONFIG_PERMISOS?.puedeVerInformacionSensible ? `
                        <div class="flex justify-between text-xs">
                            <span>Tasa:</span>
                            <span>${(this.datosCotizacion.tasa * 100).toFixed(2)}%</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>Margen:</span>
                            <span>${item.calculations.margin}</span>
                        </div>
                        <script>
                            console.log('🚨 [MARGIN DEBUG - EQUIPMENT RENDER] Equipment margin display:', {
                                'equipment_name': '${item.name}',
                                'margin_value': ${JSON.stringify(item.calculations.margin)},
                                'user_role': window.CURRENT_USER?.role,
                                'tasa_en_datos': ${JSON.stringify(this.datosCotizacion.tasa)},
                                'plazo': ${JSON.stringify(item.term)},
                                'full_calculations': ${JSON.stringify(item.calculations)}
                            });
                        </script>
                        <div class="flex justify-between text-xs">
                            <span>Costo venta:</span>
                            <span>$${item.calculations.equipmentCost.toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>Comisión:</span>
                            <span>$${item.calculations.totalCommissionAmount.toLocaleString()}</span>
                        </div>` : ''}
                    </div>
                    <div class="bg-gray-50 p-2 rounded mt-2 space-y-1">
                        <div class="font-medium text-gray-700 mb-2">Desglose Mensual:</div>
                        ${this.renderizarDesgloseMensualEquipo(item)}
                        ${(pagoGPSUnidad * item.quantity) > 0 ? `
                        <div class="flex justify-between text-xs text-purple-700">
                            <span>GPS (${item.quantity} unidad${item.quantity > 1 ? 'es' : ''}):</span>
                            <span>$${this.redondearHaciaAbajo(pagoGPSUnidad * item.quantity, 2).toLocaleString()}</span>
                        </div>` : ''}
                        ${(pagoPlacasUnidad * item.quantity) > 0 ? `
                        <div class="flex justify-between text-xs text-purple-700">
                            <span>Placas (${item.quantity} unidad${item.quantity > 1 ? 'es' : ''}):</span>
                            <span>$${this.redondearHaciaAbajo(pagoPlacasUnidad * item.quantity, 2).toLocaleString()}</span>
                        </div>` : ''}
                        <div class="flex justify-between text-xs">
                            <span>Seguro:</span>
                            <span>$${(item.calculations.monthlyInsurance || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between border-t text-xs">
                            <span>Subtotal:</span>
                            <span>$${(item.calculations.monthlySubtotal || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>IVA (16%):</span>
                            <span>$${(item.calculations.monthlyIVA || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between font-medium border-t pt-1">
                            <span>Pago Mensual:</span>
                            <span class="text-currency">$${(item.calculations.totalPaymentWithExtras || 0).toLocaleString()}</span>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-2 rounded mt-2 space-y-1">
                        <div class="font-medium text-gray-700 mb-2">Residual:</div>
                        <div class="flex justify-between text-xs">
                            <span>Valor residual:</span>
                            <span>$${(item.calculations.residualAmount || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span>IVA residual (16%):</span>
                            <span>$${(item.calculations.residualIVA || 0).toLocaleString()}</span>
                        </div>
                        <div class="border-t border-blue-200 pt-1 mt-1">
                            <div class="flex justify-between text-xs font-medium">
                                <span>1 Pago total:</span>
                                <span>$${(item.calculations.residual1Payment || 0).toLocaleString()}</span>
                            </div>
                            <div class="flex justify-between text-xs font-medium">
                                <span>3 Pagos c/u:</span>
                                <span>$${(item.calculations.residual3Payments || 0).toLocaleString()}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between font-medium mt-2 text-md border-t pt-2">
                        <span>Pago mensual acumulado:</span>
                        <span class="text-currency">$${(item.calculations.totalAgrupado || 0).toLocaleString()}</span>
                    </div>
                    <div class="flex justify-between font-medium mt-2 text-md border-t pt-2">
                        <span>Residual acumulado:</span>
                        <span class="text-currency">$${(item.calculations.totalResidual || 0).toLocaleString()}</span>
                    </div>
                </div>
            `;
            contenedor.appendChild(div);
            });
        }

        // Actualizar contador de equipos en la interfaz
        const contadorEquipos = document.getElementById('contador-equipos');
        if (contadorEquipos) {
            contadorEquipos.textContent = this.datosCotizacion.equipos.length;
        }

        this.actualizarBotonSiguiente(this.datosCotizacion.equipos.length > 0);
    }

    toggleDetallesEquipo(indice) {
        const elementoDetalles = document.querySelector(`.detalles-equipo-${indice}`);
        const elementoChevron = document.querySelector(`.chevron-equipo-${indice}`);
        
        if (elementoDetalles.classList.contains('hidden')) {
            elementoDetalles.classList.remove('hidden');
            elementoChevron.style.transform = 'rotate(180deg)';
        } else {
            elementoDetalles.classList.add('hidden');
            elementoChevron.style.transform = 'rotate(0deg)';
        }
    }

    eliminarEquipo(indice) {
        this.datosCotizacion.equipos.splice(indice, 1);
        this.render();
        this.actualizarDrawerCarrito();
        this.actualizarTotales();
    }

    // Alias para compatibilidad con el carrito
    removerEquipo(indice) {
        this.eliminarEquipo(indice);
    }

    aplicarAnticipoYRecalcular() {
        const anticipo = this.datosCotizacion.anticipo || 0;

        // PASO 1: Calcular cada equipo SIN anticipo para obtener costos de GPS/Placas
        this.datosCotizacion.equipos.forEach((item) => {
            const calculosSinAnticipo = window.controladorCalculos.calcularValoresEquipo(
                item.cost,
                this.datosCotizacion.tasa,
                item.term,
                item.residual,
                item.insuranceRate || 0.006,
                item.quantity,
                0, // Sin anticipo en este paso
                item.includeGPS,
                item.includePlacas
            );
            item.calculations = calculosSinAnticipo;
        });

        if (anticipo === 0 || this.datosCotizacion.equipos.length === 0) {
            // Si no hay anticipo, solo resetear anticipoProporcional y salir
            this.datosCotizacion.equipos.forEach(item => {
                item.anticipoProporcional = 0;
            });
            return;
        }

        // PASO 2: Calcular el monto total a financiar (ahora con GPS/Placas correctos)
        let montoTotalFinanciar = 0;
        console.log('🔍 CÁLCULO DEL MONTO TOTAL A FINANCIAR:');
        this.datosCotizacion.equipos.forEach(item => {
            const costoGps = item.calculations.costoGpsAgregado || 0;
            const costoPlacas = item.calculations.costoPlacasAgregado || 0;
            const costoBaseEquipo = item.cost + costoGps + costoPlacas;
            const costoTotalEquipo = costoBaseEquipo * item.quantity;
            montoTotalFinanciar += costoTotalEquipo;

            console.log(`  ${item.type}:`, {
                costoBase: item.cost,
                costoGPS: costoGps,
                costoPlacas: costoPlacas,
                costoBaseEquipo: costoBaseEquipo,
                cantidad: item.quantity,
                costoTotal: costoTotalEquipo,
                includeGPS: item.calculations.includeGPS,
                includePlacas: item.calculations.includePlacas
            });
        });
        console.log('💰 MONTO TOTAL A FINANCIAR:', montoTotalFinanciar);

        // PASO 3: Distribuir anticipo proporcionalmente entre los equipos
        this.datosCotizacion.equipos.forEach(item => {
            const costoGps = item.calculations.costoGpsAgregado || 0;
            const costoPlacas = item.calculations.costoPlacasAgregado || 0;
            const costoBaseEquipo = item.cost + costoGps + costoPlacas;
            const costoTotalEquipo = costoBaseEquipo * item.quantity;

            // Calcular anticipo proporcional para este equipo
            const anticipoProporcional = montoTotalFinanciar > 0
                ? (anticipo * costoTotalEquipo / montoTotalFinanciar)
                : 0;

            // Guardar el anticipo proporcional en el equipo
            item.anticipoProporcional = anticipoProporcional;
        });

        // LOG CONSOLIDADO: Mostrar desglose de anticipo por equipo
        console.log('💰 DESGLOSE DE ANTICIPO POR EQUIPO:', {
            'Anticipo Total': '$' + anticipo.toLocaleString(),
            'Monto Total a Financiar': '$' + montoTotalFinanciar.toLocaleString(),
            'Equipos': this.datosCotizacion.equipos.map(item => {
                const costoGps = item.calculations.costoGpsAgregado || 0;
                const costoPlacas = item.calculations.costoPlacasAgregado || 0;
                const costoBaseEquipo = item.cost + costoGps + costoPlacas;
                const costoTotalEquipo = costoBaseEquipo * item.quantity;
                return {
                    nombre: `${item.quantity}x ${item.type}`,
                    costoBase: '$' + costoBaseEquipo.toLocaleString(),
                    costoTotal: '$' + costoTotalEquipo.toLocaleString(),
                    porcentaje: ((costoTotalEquipo / montoTotalFinanciar) * 100).toFixed(2) + '%',
                    anticipoProporcional: '$' + (item.anticipoProporcional || 0).toLocaleString()
                };
            })
        });

        // PASO 4: Recalcular cada equipo CON el anticipo proporcional
        this.datosCotizacion.equipos.forEach((item) => {
            const calculos = window.controladorCalculos.calcularValoresEquipo(
                item.cost,
                this.datosCotizacion.tasa,
                item.term,
                item.residual,
                item.insuranceRate || 0.006,
                item.quantity,
                item.anticipoProporcional, // Pasar anticipo proporcional
                item.includeGPS,
                item.includePlacas
            );

            // Actualizar los cálculos del equipo
            item.calculations = calculos;
        });
    }

    actualizarTotales() {
        // Aplicar anticipo proporcionalmente y recalcular equipos
        this.aplicarAnticipoYRecalcular();

        let totalContrato = 0;
        let totalCostosVenta = 0;
        let totalCostosCompra = 0;
        let totalPagoMensual = 0;
        let totalSubtotal = 0;
        let totalIVA = 0;
        let montoFinanciar = 0;

        this.datosCotizacion.equipos.forEach(item => {
            // Total del contrato = pago mensual equipo (SIN seguro, SIN IVA) × plazo × cantidad de equipos
            const pagoMensualEquipoPorUnidad = item.calculations.monthlyEquipmentPayment || 0;
            const totalPagosEquipo = pagoMensualEquipoPorUnidad * item.term * item.quantity;
            totalContrato += totalPagosEquipo;

            // Para clientes: calcular pago mensual, subtotal e IVA (incluyendo placas y GPS)
            // NOTA: Los valores monthly* vienen por unidad, necesitan multiplicarse por cantidad
            const pagoMensualConIVA = item.calculations.totalMonthlyPayment || 0;
            totalPagoMensual += pagoMensualConIVA * item.quantity;
            totalSubtotal += (item.calculations.monthlySubtotal || 0) * item.quantity;
            totalIVA += (item.calculations.monthlyIVA || 0) * item.quantity;

            // Costos de venta y compra para cálculo de utilidad
            const costoVentaEquipo = item.calculations.saleCost * item.quantity; // Costo venta × cantidad
            const costoCompraEquipo = item.cost * item.quantity; // Costo producto × cantidad

            totalCostosVenta += costoVentaEquipo;
            totalCostosCompra += costoCompraEquipo;

            // Calcular monto a financiar: (precio + GPS + Placas) × cantidad (SIN IVA)
            const costoGps = item.calculations.costoGpsAgregado || 0;
            const costoPlacas = item.calculations.costoPlacasAgregado || 0;
            const costoBaseEquipo = item.cost + costoGps + costoPlacas;
            montoFinanciar += costoBaseEquipo * item.quantity;
        });

        // Calcular utilidad usando la fórmula correcta: 1 - (totalCostosCompra / totalCostosVenta)
        const utilidadCalculada = totalCostosVenta > 0 ? parseFloat((1 - (totalCostosCompra / totalCostosVenta)).toFixed(2)) : 0;

        // Restar anticipo solo del monto a financiar (NO del total del contrato)
        const anticipo = this.datosCotizacion.anticipo || 0;
        const montoFinanciarFinal = montoFinanciar - anticipo;

        this.datosCotizacion.totales = {
            contrato: Math.floor(totalContrato * 100) / 100,
            utilidad: utilidadCalculada, // Utilidad como porcentaje decimal (0.11 = 11%)
            costosVenta: totalCostosVenta,
            costosCompra: totalCostosCompra,
            pagoMensual: Math.floor(totalPagoMensual * 100) / 100,
            subtotal: Math.floor(totalSubtotal * 100) / 100,
            iva: Math.floor(totalIVA * 100) / 100,
            montoFinanciar: Math.floor(montoFinanciarFinal * 100) / 100
        };

        // Debug: mostrar totales calculados para clientes
        if (window.CURRENT_USER && window.CURRENT_USER.role === 'client') {
            console.log('🔢 Totales calculados para cliente:', {
                pagoMensual: this.datosCotizacion.totales.pagoMensual,
                subtotal: this.datosCotizacion.totales.subtotal,
                iva: this.datosCotizacion.totales.iva,
                contrato: this.datosCotizacion.totales.contrato
            });
        }

        // Actualizar footer según el rol
        if (window.CURRENT_USER && window.CURRENT_USER.role === 'client') {
            // Footer para clientes: pago mensual, subtotal, IVA
            this.actualizarFooterCliente();
        } else {
            // Footer para vendedores/admin: total del contrato
            const totalElement = document.getElementById('total-pie');
            const nuevoTotal = `$${this.datosCotizacion.totales.contrato.toLocaleString()}`;

            // Solo animar si el total realmente cambió
            if (totalElement && totalElement.textContent !== nuevoTotal) {
                totalElement.textContent = nuevoTotal;

                // Agregar animación
                totalElement.classList.add('total-animate');
                setTimeout(() => {
                    totalElement.classList.remove('total-animate');
                }, 300);
            }
        }
        
        this.actualizarDrawerCarrito();
    }

    actualizarFooterCliente() {
        const pagoMensualElement = document.getElementById('pago-mensual-pie');
        const subtotalElement = document.getElementById('subtotal-pie');
        const ivaElement = document.getElementById('iva-pie');

        if (pagoMensualElement) {
            const nuevoPagoMensual = `$${this.datosCotizacion.totales.pagoMensual.toLocaleString()}`;
            
            if (pagoMensualElement.textContent !== nuevoPagoMensual) {
                pagoMensualElement.textContent = nuevoPagoMensual;
                
                // Agregar animación
                pagoMensualElement.classList.add('total-animate');
                setTimeout(() => {
                    pagoMensualElement.classList.remove('total-animate');
                }, 300);
            }
        }

        if (subtotalElement) {
            subtotalElement.textContent = `$${this.datosCotizacion.totales.subtotal.toLocaleString()}`;
        }

        if (ivaElement) {
            ivaElement.textContent = `$${this.datosCotizacion.totales.iva.toLocaleString()}`;
        }
    }

    siguienteFase() {
        if (this.validarFaseActual() && this.faseActual < this.totalFases) {
            // Si estamos en Fase 4, mostrar confirmación antes de finalizar
            if (this.faseActual === 4) {
                this.confirmarFinalizarCotizacion();
                return;
            }
            
            this.faseActual++;
            this.mostrarFase(this.faseActual);
            
            // El guardado se maneja en confirmarFinalizarCotizacion() para fase 4->5
        }
    }

    async confirmarFinalizarCotizacion() {
        const confirmar = confirm('¿Estás seguro de que deseas finalizar esta cotización?\n\nUna vez finalizada, no podrás hacer cambios.');
        
        if (confirmar) {
            // Mostrar loader mientras se procesa
            if (window.loaderManager) {
                window.loaderManager.show('Finalizando cotización...');
            }
            
            try {
                // Avanzar a Fase 5
                this.faseActual++;
                this.mostrarFase(this.faseActual);
                
                // Guardar la cotización automáticamente
                await this.guardarCotizacionFinal();
                
                if (window.loaderManager) {
                    window.loaderManager.hide();
                }
            } catch (error) {
                if (window.loaderManager) {
                    window.loaderManager.hide();
                }
                console.error('Error al finalizar cotización:', error);
                this.mostrarError('Error al finalizar la cotización');
            }
        }
    }

    faseAnterior() {
        if (this.faseActual > 1) {
            this.faseActual--;
            this.mostrarFase(this.faseActual);
        }
    }

    validarFaseActual() {
        switch (this.faseActual) {
            case 1:
                return this.validarFase1();
            case 2:
                return this.validarFase2();
            case 3:
                return this.datosCotizacion.equipos.length > 0;
            default:
                return true;
        }
    }

    mostrarFase(fase) {
        document.querySelectorAll('.contenido-fase').forEach(el => {
            el.classList.add('hidden');
        });

        const faseActualEl = document.getElementById(`fase-${fase}`);
        if (faseActualEl) {
            faseActualEl.classList.remove('hidden');
        }

        const progreso = ((fase) / this.totalFases) * 100;
        document.getElementById('barra-progreso').style.width = `${progreso}%`;
        document.getElementById('paso-actual').textContent = fase;

        document.getElementById('boton-anterior').disabled = fase <= 1;
        
        // Manejar botones según la fase
        const botonSiguiente = document.getElementById('boton-siguiente');
        const botonAnterior = document.getElementById('boton-anterior');
        const botonAtras = document.getElementById('boton-atras');
        
        if (fase >= this.totalFases || fase === 0) {
            // Fase 0 y Fase 5: Ocultar todo el footer de navegación
            botonSiguiente.style.display = 'none';
            botonAnterior.style.display = 'none';
            if (botonAtras) botonAtras.classList.add('hidden');
            
            // Ocultar todo el footer de navegación en fase inicial y final
            const navigationFooter = document.querySelector('footer');
            if (navigationFooter) navigationFooter.style.display = 'none';
        } else if (fase === 4) {
            // Fase 4: Cambiar texto a "Finalizar Cotización"
            botonSiguiente.style.display = 'block';
            botonAnterior.style.display = 'block';
            if (botonAtras) botonAtras.classList.toggle('hidden', fase === 0);
            botonSiguiente.textContent = 'Finalizar Cotización';
            botonSiguiente.classList.add('bg-green-600', 'hover:bg-green-700');
            botonSiguiente.classList.remove('bg-accent', 'hover:bg-blue-600');
            
            // Mostrar footer e info destacada en fase 4
            const navigationFooter = document.querySelector('footer');
            if (navigationFooter) navigationFooter.style.display = 'block';
            
            const infoDestacada = document.getElementById('info-destacada');
            if (infoDestacada) infoDestacada.classList.remove('hidden');
        } else {
            // Fases 1-3: Botón normal "Siguiente"
            botonSiguiente.style.display = 'block';
            botonAnterior.style.display = 'block';
            if (botonAtras) botonAtras.classList.toggle('hidden', fase === 0);
            botonSiguiente.textContent = 'Siguiente';
            botonSiguiente.classList.add('bg-accent', 'hover:bg-blue-600');
            botonSiguiente.classList.remove('bg-green-600', 'hover:bg-green-700');
            
            // Mostrar footer e info destacada en fases normales
            const navigationFooter = document.querySelector('footer');
            if (navigationFooter) navigationFooter.style.display = 'block';
            
            const infoDestacada = document.getElementById('info-destacada');
            if (infoDestacada) infoDestacada.classList.remove('hidden');
        }

        const titulos = ['Cotizaciones', 'Cliente', 'Términos', 'Equipos', 'Resumen', 'Confirmación'];
        const subtitulos = [
            'Sistema de cotizaciones', 
            'Información del cliente', 
            'Términos del contrato', 
            'Selección de equipos', 
            'Revisión final', 
            'Cotización completada'
        ];
        
        document.getElementById('titulo-fase').textContent = titulos[fase] || 'Cotizador';
        
        const subtituloElement = document.getElementById('subtitulo-fase');
        if (subtituloElement) {
            subtituloElement.textContent = subtitulos[fase] || 'WISER Cotizador';
        }

        this.manejarTransicionFase(fase);
        
        // Actualizar contador de equipos específicamente en fase 3
        if (fase === 3) {
            const contadorEquipos = document.getElementById('contador-equipos');
            if (contadorEquipos) {
                contadorEquipos.textContent = this.datosCotizacion.equipos.length;
            }
        }
        
        this.faseActual = fase;
    }

    manejarTransicionFase(fase) {
        switch (fase) {
            case 2:
                this.cargarCatalogoPlazos();
                break;
            case 3:
                this.actualizarTerminosMostrados();
                break;
            case 4:
                this.renderResumen();
                break;
            case 5:
                // El guardado ya se hizo en confirmarFinalizarCotizacion()
                // Solo mostrar la confirmación final
                this.mostrarConfirmacionFinal();
                break;
        }
    }

    renderResumen() {
        // console.log('🔍 [RESUMEN] Iniciando renderResumen - Timestamp:', new Date().toISOString());
        // console.log('📊 [RESUMEN] Datos completos:', this.datosCotizacion);
        // console.log('🛠️ [RESUMEN] Total equipos:', this.datosCotizacion.equipos.length);
        
        const contenedor = document.getElementById('contenido-resumen');
        if (!contenedor) {
            console.error('❌ [RESUMEN] FATAL: No se encontró el contenedor contenido-resumen');
            alert('Error: No se encontró el contenedor del resumen. Verificar HTML.');
            return;
        }
        
        // console.log('✅ [RESUMEN] Contenedor encontrado:', contenedor);
        
        // Verificar si hay equipos
        if (!this.datosCotizacion.equipos || this.datosCotizacion.equipos.length === 0) {
            console.warn('⚠️ [RESUMEN] No hay equipos para mostrar');
            contenedor.innerHTML = `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800">⚠️ No hay equipos agregados para mostrar en el resumen.</p>
                </div>
            `;
            return;
        }
        
        // Construir HTML del resumen
        let html = `
            <div class="space-y-4">
                <!-- Información del Cliente -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Cliente</h4>
                    <p class="text-sm text-gray-600">Nombre: ${this.datosCotizacion.nombreCliente || 'No definido'}</p>
                    ${window.CONFIG_PERMISOS?.puedeVerInformacionSensible ? `
                    <p class="text-sm text-gray-600">Tipo: ${this.datosCotizacion.tipoCliente || 'No definido'} (${((this.datosCotizacion.tasa || 0) * 100).toFixed(2)}%)</p>` : ''}
                    <p class="text-sm text-gray-600">Moneda: ${this.datosCotizacion.moneda || 'MXN'}</p>
                </div>
                
                <!-- Términos Globales -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Términos Globales</h4>
                    <p class="text-sm text-gray-600">Plazo: ${this.datosCotizacion.plazoGlobal || 0} meses</p>
                    <p class="text-sm text-gray-600">Residual: ${this.datosCotizacion.residualGlobal || 20}%</p>
                </div>

                <!-- Lista de Equipos -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Equipos (${this.datosCotizacion.equipos.length})</h4>
                    <div class="space-y-3">
        `;

        // Procesar cada equipo
        this.datosCotizacion.equipos.forEach((equipo, index) => {
            const calculations = equipo.calculations || {};
            const totalMonthlyPayment = calculations.totalMonthlyPayment || calculations.totalPayment || 0;
            const totalEquipmentCost = calculations.totalEquipmentCost || calculations.totalSaleCost || 0;
            // CORREGIDO: usar totalMonthlyPayment que viene por unidad, multiplicar por cantidad
            const pagoMensual = totalMonthlyPayment * equipo.quantity;

            // Total del contrato = pago equipo mensual (SIN seguro, SIN IVA) × plazo × cantidad
            const pagoEquipoMensual = calculations.monthlyEquipmentPayment || 0;
            const totalContratoEquipo = pagoEquipoMensual * equipo.term * equipo.quantity;
            
            // Calcular valores para el residual (por unidad)
            const valorResidualUnidad = calculations.residualAmount || (equipo.cost * (equipo.residual / 100));
            const ivaResidualUnidad = valorResidualUnidad * 0.16; // 16% IVA
            const unPagoTotalUnidad = valorResidualUnidad + ivaResidualUnidad;
            const tresPagosCadaUnoUnidad = unPagoTotalUnidad / 3 * 1.1;

            html += `
                <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
                    <!-- Header del equipo -->
                    <div class="p-4 bg-gray-50 border-b">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h5 class="font-semibold text-gray-900 mb-1">
                                    ${equipo.quantity || 1}x ${equipo.typeDisplay || equipo.type || 'Equipo sin tipo'}
                                </h5>
                                <p class="text-sm text-gray-600">
                                    ${equipo.brandDisplay || equipo.brand || 'Sin marca'}${equipo.modelDisplay ? ` - ${equipo.modelDisplay}` : ''}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-blue-600">
                                    $${pagoMensual.toLocaleString()}<span class="text-sm font-normal text-gray-500">/mes</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Información detallada del equipo -->
                    <div class="p-4 space-y-4">
                        <!-- Información del Equipo -->
                        <div class="border-l-4 border-blue-500 pl-4 bg-blue-50 p-3 rounded-r-lg">
                            <h6 class="font-medium text-gray-900 text-sm mb-2">Información del Equipo</h6>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Costo unitario:</span>
                                    <span class="font-medium">$${(equipo.cost || 0).toLocaleString()}</span>
                                </div>
                                ${equipo.anticipoProporcional && equipo.anticipoProporcional > 0 ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Anticipo (proporcional):</span>
                                    <span class="font-medium text-green-600">-$${(equipo.anticipoProporcional || 0).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between border-t border-blue-200 pt-1 mt-1">
                                    <span class="text-gray-600">Costo financiado:</span>
                                    <span class="font-medium">$${((equipo.cost * equipo.quantity) - (equipo.anticipoProporcional || 0)).toLocaleString()}</span>
                                </div>` : ''}
                                ${window.CURRENT_USER && window.CURRENT_USER.role === 'client' ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Pago mensual:</span>
                                    <span class="font-semibold text-green-600">$${pagoMensual.toLocaleString()}</span>
                                </div>` : `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total contrato:</span>
                                    <span class="font-semibold text-green-600">$${(Math.floor(totalContratoEquipo * 100) / 100).toLocaleString()}</span>
                                </div>`}
                            </div>
                        </div>

                        <!-- Desglose Mensual -->
                        <div class="border-l-4 border-blue-500 pl-4 bg-blue-50 p-3 rounded-r-lg">
                            <h6 class="font-medium text-gray-900 text-sm mb-2">Pago mensual</h6>
                            <div class="space-y-1 text-xs">
                                ${this.renderizarDesgloseMensualResumen(equipo)}
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Seguro:</span>
                                    <span class="font-medium">$${((calculations.monthlyInsurance || 0) * equipo.quantity).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between border-t border-blue-200 pt-1 mt-1">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium">$${((calculations.monthlySubtotal || 0) * equipo.quantity).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">IVA (16%):</span>
                                    <span class="font-medium">$${((calculations.monthlyIVA || 0) * equipo.quantity).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between border-t border-blue-200 pt-1 mt-1">
                                    <span class="text-gray-600">Pago mensual total:</span>
                                    <span class="font-semibold text-blue-600">$${pagoMensual.toLocaleString()}</span>
                                </div>
                                ${window.CONFIG_PERMISOS?.puedeVerInformacionSensible ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Margen de venta:</span>
                                    <span class="font-medium">${(calculations.margin || 1).toFixed(4)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Costo de venta:</span>
                                    <span class="font-medium">$${(calculations.saleCost || 0).toLocaleString()}</span>
                                </div>` : ''}
                            </div>
                        </div>

                        <!-- Información del Residual -->
                        <div class="border-l-4 border-blue-500 pl-4 bg-blue-50 p-3 rounded-r-lg">
                            <h6 class="font-medium text-gray-900 text-sm mb-2">Información del Residual</h6>
                            <div class="space-y-1 text-xs">                               
                                <div class="flex justify-between border-t border-blue-200 pt-1 mt-1">
                                    <span class="text-gray-600">1 Pago total:</span>
                                    <span class="font-semibold text-purple-600">$${(unPagoTotalUnidad * equipo.quantity).toLocaleString()}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">3 Pagos c/u:</span>
                                    <span class="font-semibold text-purple-600">$${(tresPagosCadaUnoUnidad * equipo.quantity).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        // Cerrar sección de equipos y agregar totales
        html += `
                    </div>
                </div>

                <!-- Totales Generales -->
                <div class="bg-blue-50 p-4 rounded-lg border-2 border-blue-200">
                    ${window.CURRENT_USER && window.CURRENT_USER.role === 'client' ? `
                    <h4 class="font-medium text-blue-900 mb-3">Totales Mensuales</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-base font-medium">Pago Mensual Total:</span>
                            <span class="text-2xl font-bold text-blue-900">$${(this.datosCotizacion.totales.pagoMensual || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-blue-200">
                            <span class="text-sm text-gray-700">Subtotal:</span>
                            <span class="text-lg font-semibold text-gray-700">$${(this.datosCotizacion.totales.subtotal || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700">IVA (16%):</span>
                            <span class="text-lg font-semibold text-gray-700">$${(this.datosCotizacion.totales.iva || 0).toLocaleString()}</span>
                        </div>
                    </div>` : `
                    <h4 class="font-medium text-blue-900 mb-3">Totales del Contrato</h4>
                    <div class="space-y-2">
                        ${this.datosCotizacion.anticipo > 0 ? `
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700">Anticipo:</span>
                            <span class="text-lg font-semibold text-green-600">$${(this.datosCotizacion.anticipo || 0).toLocaleString()}</span>
                        </div>` : ''}
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-700">Monto a Financiar:</span>
                            <span class="text-lg font-semibold text-blue-600">$${(this.datosCotizacion.totales.montoFinanciar || 0).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-blue-200">
                            <span class="text-base font-medium">Total del Contrato:</span>
                            <span class="text-2xl font-bold text-blue-900">$${(this.datosCotizacion.totales.contrato || 0).toLocaleString()}</span>
                        </div>
                        ${window.CONFIG_PERMISOS?.puedeVerInformacionSensible ? `
                        <div class="flex justify-between items-center pt-2 border-t border-blue-200">
                            <span class="text-sm text-gray-700">Utilidad estimada:</span>
                            <span class="text-lg font-semibold text-green-600">${((this.datosCotizacion.totales.utilidad || 0) * 100).toFixed(1)}%</span>
                        </div>` : ''}
                    </div>`}
                </div>
            </div>
        `;

        // Insertar el HTML generado
        // console.log('📝 [RESUMEN] Insertando HTML generado...');
        contenedor.innerHTML = html;
        // console.log('✅ [RESUMEN] Resumen renderizado exitosamente!');
        
        // Forzar repintado
        contenedor.style.opacity = '0';
        setTimeout(() => {
            contenedor.style.opacity = '1';
        }, 10);
    }

    configurarInterfazSegunRol() {
        // Configurar interfaz según el rol del usuario
        if (window.CURRENT_USER && window.CURRENT_USER.role === 'client') {
            // Ocultar sección de total contrato y mostrar desglose cliente
            const totalContratoSection = document.getElementById('total-contrato-section');
            const desgloseClienteSection = document.getElementById('desglose-cliente-section');
            
            if (totalContratoSection) totalContratoSection.classList.add('hidden');
            if (desgloseClienteSection) desgloseClienteSection.classList.remove('hidden');
        }
    }

    async configurarValoresCliente() {
        try {
            // Buscar el tipo de cliente predefinido para portal (Activo = 2)
            const tipoClientePortal = await this.obtenerTipoClientePortal();
            
            if (tipoClientePortal) {
                // Usar datos del tipo de cliente portal
                this.datosCotizacion.tipoCliente = tipoClientePortal.Codigo;
                this.datosCotizacion.tasa = parseFloat(tipoClientePortal.Tasa) / 12; // Convertir tasa anual a mensual
                this.datosCotizacion.comision = parseFloat(tipoClientePortal.Comision);
                console.log('✅ Cliente portal configurado:', {
                    codigo: tipoClientePortal.Codigo,
                    tasa: (this.datosCotizacion.tasa * 100).toFixed(2) + '%',
                    comision: (this.datosCotizacion.comision * 100) + '%'
                });
            } else {
                // Fallback a valores por defecto si no se encuentra el tipo portal
                this.datosCotizacion.tasa = 0.024 / 12; // 2.4% anual convertido a mensual
                this.datosCotizacion.comision = 0.01;
                this.datosCotizacion.tipoCliente = 'X'; // Código conocido del SQL
                console.log('⚠️ Usando valores por defecto para cliente portal');
            }
            
            // Configurar otros valores por defecto
            this.datosCotizacion.nombreCliente = window.CURRENT_USER.fullName;
            this.datosCotizacion.moneda = 'MXN';
            
            // Actualizar elementos de UI si existen
            const selectMoneda = document.getElementById('tipo-moneda');
            if (selectMoneda) {
                selectMoneda.value = 'MXN';
            }
            
        } catch (error) {
            console.error('❌ Error configurando cliente portal:', error);
            // Fallback seguro
            this.datosCotizacion.tasa = 0.024 / 12; // 2.4% anual convertido a mensual
            this.datosCotizacion.comision = 0.01;
            this.datosCotizacion.tipoCliente = 'X';
            this.datosCotizacion.nombreCliente = window.CURRENT_USER.fullName;
            this.datosCotizacion.moneda = 'MXN';
        }
    }

    async obtenerTipoClientePortal() {
        try {
            const respuesta = await fetch('api/obtener_tipo_cliente_portal.php');
            
            if (!respuesta.ok) {
                throw new Error(`Error HTTP: ${respuesta.status}`);
            }
            
            const datos = await respuesta.json();
            
            if (datos.status === 'success' && datos.data) {
                return datos.data;
            } else {
                throw new Error(datos.message || 'No se encontró tipo de cliente portal');
            }
        } catch (error) {
            console.error('Error obteniendo tipo cliente portal:', error);
            return null; // Fallback será manejado por configurarValoresCliente
        }
    }

    async iniciarNuevaCotizacion() {
        this.limpiarCotizacion();
        
        // Mostrar footer de navegación al iniciar nueva cotización
        const navigationFooter = document.querySelector('footer');
        if (navigationFooter) navigationFooter.style.display = 'block';
        
        // Configurar valores del cliente predefinido para todos los roles
        await this.configurarValoresCliente();
        
        // Si es cliente, saltar a Fase 2
        if (window.CURRENT_USER && window.CURRENT_USER.role === 'client') {
            this.mostrarFase(2);
        } else {
            this.mostrarFase(1);
        }
    }

    limpiarCotizacion() {
        this.datosCotizacion = {
            userId: window.CURRENT_USER ? window.CURRENT_USER.id : null,
            tipoCliente: '',
            tipoClienteDescripcion: '',
            nombreCliente: '',
            tasa: 0,
            comision: 0,
            moneda: 'MXN',
            plazoGlobal: 0,
            residualGlobal: 20,
            equipos: [],
            totales: {
                contrato: 0,
                utilidad: 0
            },
            tasaPersonalizada: false,
            anticipo: 0
        };

        document.getElementById('tipo-cliente').value = '';
        document.getElementById('nombre-cliente').value = '';
        document.getElementById('tipo-moneda').value = 'MXN';

        // Resetear anticipo
        const anticipoNo = document.getElementById('anticipo-no');
        if (anticipoNo) {
            anticipoNo.checked = true;
        }
        const contenedorAnticipo = document.getElementById('contenedor-anticipo');
        if (contenedorAnticipo) {
            contenedorAnticipo.classList.add('hidden');
        }
        const montoAnticipo = document.getElementById('monto-anticipo');
        if (montoAnticipo) {
            montoAnticipo.value = '';
        }

        // Ocultar campo de tasa personalizada
        const contenedorTasaPersonalizada = document.getElementById('contenedor-tasa-personalizada');
        if (contenedorTasaPersonalizada) {
            contenedorTasaPersonalizada.classList.add('hidden');
        }
        const inputTasaPersonalizada = document.getElementById('tasa-personalizada');
        if (inputTasaPersonalizada) {
            inputTasaPersonalizada.value = '';
        }

        // Solo limpiar tasa si el elemento existe (vendedores)
        const tasaElemento = document.getElementById('tasa-actual');
        if (tasaElemento) {
            tasaElemento.textContent = '0%';
        }

        this.limpiarFormularioEquipo();
        document.getElementById('lista-equipos').innerHTML = '';

        document.getElementById('total-pie').textContent = '$0.00';
        this.actualizarFooterInfo();

        this.actualizarDrawerCarrito();
    }

    actualizarBotonSiguiente(habilitado) {
        const botonSiguiente = document.getElementById('boton-siguiente');
        botonSiguiente.disabled = !habilitado;
    }

    configurarAutoguardado() {
        // Autoguardado desactivado - solo se guarda al finalizar en Fase 5
        // this.temporizadorAutoguardado = setInterval(() => {
        //     this.autoguardar();
        // }, 30000);
    }

    async autoguardar() {
        if (this.datosCotizacion.equipos.length === 0) return;

        try {
            const respuesta = await fetch('api/guardar_borrador.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(this.datosCotizacion)
            });

            const datos = await respuesta.json();
            if (datos.status === 'success') {
                this.idCotizacionActual = datos.quoteId;
            }
        } catch (error) {
            console.error('Error en autoguardado:', error);
        }
    }

    actualizarDrawerCarrito() {
        if (window.controladorCarrito) {
            const terminosGlobales = {
                term: this.datosCotizacion.plazoGlobal,
                residual: this.datosCotizacion.residualGlobal
            };
            window.controladorCarrito.actualizarCarrito(this.datosCotizacion.equipos, this.datosCotizacion.totales, terminosGlobales);
        }
    }

    mostrarCargando(mostrar) {
        const cargando = document.getElementById('cargando');
        cargando.classList.toggle('hidden', !mostrar);
        cargando.classList.toggle('flex', mostrar);
    }

    mostrarError(mensaje) {
        console.error(mensaje);
        alert(mensaje);
    }

    mostrarExito(mensaje) {
        console.log('✅ ' + mensaje);
        alert(mensaje);
    }

    renderizarDesgloseMensualEquipo(item) {
        const calc = item.calculations;
        let html = '';

        // USAR VALORES DE LA BD (ya calculados con interés en calculations.js)
        const pagoGPSUnidad = calc.pagoGPS || 0;
        const pagoPlacasUnidad = calc.pagoPlacas || 0;
        const pagoEquipoUnidad = calc.monthlyEquipmentPayment || 0;

        // El pago del equipo YA NO incluye GPS ni Placas (se calculan por separado)
        const equipoSolo = pagoEquipoUnidad;

        // Mostrar Equipo (sin GPS ni Placas)
        html += `
            <div class="flex justify-between text-xs">
                <span>Equipo:</span>
                <span>$${this.redondearHaciaAbajo(equipoSolo, 2).toLocaleString()}</span>
            </div>
        `;

        // Mostrar GPS si está incluido (solo si > 0)
        if (pagoGPSUnidad > 0) {
            html += `
                <div class="flex justify-between text-xs">
                    <span>GPS:</span>
                    <span>$${this.redondearHaciaAbajo(pagoGPSUnidad, 2).toLocaleString()}</span>
                </div>
            `;
        }

        // Mostrar Placas si están incluidas (solo si > 0)
        if (pagoPlacasUnidad > 0) {
            html += `
                <div class="flex justify-between text-xs">
                    <span>Placas:</span>
                    <span>$${this.redondearHaciaAbajo(pagoPlacasUnidad, 2).toLocaleString()}</span>
                </div>
            `;
        }

        return html;
    }

    renderizarDesgloseMensualResumen(equipo) {
        const calc = equipo.calculations;
        const quantity = equipo.quantity;
        let html = '';

        // USAR VALORES DE LA BD (ya calculados con interés)
        const pagoGPSUnidad = calc.pagoGPS || 0;
        const pagoPlacasUnidad = calc.pagoPlacas || 0;
        const pagoEquipoUnidad = calc.monthlyEquipmentPayment || 0;

        // Multiplicar por cantidad
        const pagoGPSTotal = pagoGPSUnidad * quantity;
        const pagoPlacasTotal = pagoPlacasUnidad * quantity;

        // El pago del equipo YA NO incluye GPS ni Placas (se calculan por separado en calculations.js)
        const equipoSinExtras = pagoEquipoUnidad * quantity;

        // Mostrar Equipo (sin GPS ni Placas, sin Seguro)
        html += `
            <div class="flex justify-between">
                <span class="text-gray-600">Equipo:</span>
                <span class="font-medium">$${this.redondearHaciaAbajo(equipoSinExtras, 2).toLocaleString()}</span>
            </div>
        `;

        // Mostrar GPS si está incluido (solo si > 0)
        if (pagoGPSTotal > 0) {
            html += `
                <div class="flex justify-between">
                    <span class="text-gray-600">GPS:</span>
                    <span class="font-medium">$${this.redondearHaciaAbajo(pagoGPSTotal, 2).toLocaleString()}</span>
                </div>
            `;
        }

        // Mostrar Placas si están incluidas (solo si > 0)
        if (pagoPlacasTotal > 0) {
            html += `
                <div class="flex justify-between">
                    <span class="text-gray-600">Placas:</span>
                    <span class="font-medium">$${this.redondearHaciaAbajo(pagoPlacasTotal, 2).toLocaleString()}</span>
                </div>
            `;
        }

        return html;
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

    redondearDecimales(valor, decimales) {
        const factor = Math.pow(10, decimales);
        return Math.round(valor * factor) / factor;
    }

    // Función para redondear hacia abajo (para pagos mensuales)
    redondearHaciaAbajo(valor, decimales) {
        const factor = Math.pow(10, decimales);
        return Math.floor(valor * factor) / factor;
    }

    actualizarCalculosEnVivo() {
        if (window.controladorCalculos) {
            window.controladorCalculos.actualizarCalculosEnVivo();
        }
    }

    async guardarCotizacion() {
        try {
            console.log('💾 Guardando cotización...', this.datosCotizacion);
            
            const payload = {
                currentQuoteId: this.idCotizacionActual,
                userId: this.datosCotizacion.userId,
                tipoCliente: this.datosCotizacion.tipoCliente,
                nombreCliente: this.datosCotizacion.nombreCliente,
                tasa: this.datosCotizacion.tasa,
                moneda: this.datosCotizacion.moneda,
                equipos: this.datosCotizacion.equipos,
                totales: this.datosCotizacion.totales,
                anticipo: this.datosCotizacion.anticipo || 0,
                estado: 'creada'  // Estado final de la cotización
            };

            const respuesta = await fetch('api/guardar_borrador.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            if (!respuesta.ok) {
                throw new Error(`Error HTTP: ${respuesta.status}`);
            }

            const datos = await respuesta.json();
            console.log('📄 Respuesta del servidor:', datos);
            
            if (datos.status === 'success') {
                this.idCotizacionActual = datos.data.quoteId;
                console.log('✅ Cotización guardada con ID:', this.idCotizacionActual);
                return datos;
            } else {
                throw new Error(datos.message || 'Error desconocido al guardar');
            }
        } catch (error) {
            console.error('❌ Error guardando cotización:', error);
            this.mostrarError('Error al guardar la cotización: ' + error.message);
            throw error;
        }
    }

    mostrarConfirmacionFinal() {
        const contenedor = document.getElementById('fase-5');
        
        const html = `
            <div class="text-center space-y-6">
                <div class="bg-green-50 p-6 rounded-lg border border-green-200">
                    <div class="flex justify-center mb-4">
                        <div class="bg-green-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold text-green-900 mb-2">¡Cotización Finalizada!</h3>
                    <p class="text-green-700">Tu cotización ha sido guardada exitosamente.</p>
                    ${this.idCotizacionActual ? `<p class="text-sm text-green-600 mt-2">ID de Cotización: #${this.idCotizacionActual}</p>` : ''}
                </div>
                
                <!-- Sección de QR y Enlace Compartido -->
                <div id="seccion-enlace-compartido" class="bg-blue-50 p-6 rounded-lg border border-blue-200">
                    <div class="flex justify-center mb-4">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-blue-900 mb-2">Enlace para Compartir</h4>
                    <p class="text-blue-700 text-sm mb-4">Comparte esta cotización con tu cliente usando el QR o enlace</p>
                    
                    <div class="space-y-4">
                        <!-- QR Code -->
                        <div id="contenedor-qr" class="hidden">
                            <div class="bg-white p-4 rounded-lg inline-block">
                                <img id="imagen-qr" src="" alt="Código QR" class="w-32 h-32 mx-auto">
                            </div>
                            <p class="text-xs text-blue-600 mt-2">Folio: <span id="folio-compartido"></span></p>
                            <p class="text-xs text-blue-500">Válido por 24 horas</p>
                        </div>
                        
                        <!-- Enlace -->
                        <div id="contenedor-enlace" class="hidden">
                            <div class="bg-white p-3 rounded border text-left">
                                <div class="flex items-center justify-between">
                                    <input type="text" id="enlace-publico" readonly 
                                           class="flex-1 text-xs text-gray-600 bg-transparent border-none outline-none mr-2">
                                    <button id="btn-copiar-enlace" 
                                            class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        Copiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botón generar -->
                        <button id="btn-generar-enlace" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                            Generar Enlace Compartido
                        </button>
                        
                        <!-- Sección de envío por email -->
                        ${window.EMAIL_ENABLED ? `
                        <div id="seccion-envio-email" class="border-t pt-4 mt-4 hidden">
                            <h5 class="text-md font-medium text-blue-900 mb-3">Enviar por Email</h5>

                            <!-- Campo email para admin/vendor -->
                            <div id="contenedor-email-destino" class="mb-3 hidden">
                                <input type="email" id="email-destino" placeholder="Ingrese email del cliente"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                <p class="text-xs text-gray-500 mt-1">Se enviará la cotización a esta dirección</p>
                            </div>

                            <!-- Info para cliente -->
                            <div id="info-email-cliente" class="mb-3 hidden">
                                <p class="text-sm text-blue-700">Se enviará a tu email registrado: <span id="email-usuario-actual" class="font-medium"></span></p>
                            </div>

                            <button id="btn-enviar-email"
                                    class="w-full bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Enviar Cotización por Email
                            </button>
                        </div>
                        ` : ''}
                        
                        <!-- Loading -->
                        <div id="loading-enlace" class="hidden">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                            <p class="text-xs text-blue-600 mt-2">Generando enlace...</p>
                        </div>
                        
                        <!-- Loading Email -->
                        ${window.EMAIL_ENABLED ? `
                        <div id="loading-email" class="hidden">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600 mx-auto"></div>
                            <p class="text-xs text-green-600 mt-2">Enviando email...</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <div class="space-y-4">
                    <button id="btn-nueva-cotizacion" class="w-full bg-accent text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-colors font-medium">
                        Nueva Cotización
                    </button>
                </div>
            </div>
        `;

        contenedor.innerHTML = html;
        
        // Agregar event listeners
        const btnNuevaCotizacion = document.getElementById('btn-nueva-cotizacion');
        if (btnNuevaCotizacion) {
            btnNuevaCotizacion.addEventListener('click', () => this.nuevaCotizacion());
        }
        
        // Event listeners para enlace compartido
        const btnGenerarEnlace = document.getElementById('btn-generar-enlace');
        if (btnGenerarEnlace) {
            btnGenerarEnlace.addEventListener('click', () => this.generarEnlaceCompartido());
        }
        
        const btnCopiarEnlace = document.getElementById('btn-copiar-enlace');
        if (btnCopiarEnlace) {
            btnCopiarEnlace.addEventListener('click', () => this.copiarEnlace());
        }
        
        // Event listener para envío por email
        if (window.EMAIL_ENABLED) {
            const btnEnviarEmail = document.getElementById('btn-enviar-email');
            if (btnEnviarEmail) {
                btnEnviarEmail.addEventListener('click', () => this.enviarCotizacionPorEmail());
            }

            // Mostrar sección de email y configurar según el rol del usuario
            this.configurarSeccionEmail();
        }
        
        // La sección de enlace compartido se muestra siempre
    }

    // Métodos para la fase final
    async imprimirCotizacion() {
        try {
            // Si no hay ID, intentar guardar primero
            if (!this.idCotizacionActual) {
                console.log('🔄 No hay ID de cotización, guardando antes de imprimir...');
                await this.guardarCotizacion();
            }

            if (this.idCotizacionActual) {
                console.log('🖨️ Abriendo impresión para cotización ID:', this.idCotizacionActual);
                window.open(`api/imprimir.php?id=${this.idCotizacionActual}`, '_blank');
            } else {
                this.mostrarError('No se pudo obtener el ID de la cotización para imprimir');
            }
        } catch (error) {
            console.error('❌ Error al intentar imprimir:', error);
            this.mostrarError('Error al preparar la cotización para imprimir');
        }
    }

    async guardarCotizacionFinal() {
        try {
            console.log('💾 Guardando cotización final...', this.datosCotizacion);
            
            // Validar que la cotización tenga datos mínimos
            if (!this.datosCotizacion.nombreCliente || this.datosCotizacion.equipos.length === 0) {
                this.mostrarError('La cotización debe tener un cliente y al menos un equipo');
                return;
            }

            // Validar que tengamos un tipo de cliente válido
            if (!this.datosCotizacion.tipoCliente) {
                this.mostrarError('Tipo de cliente es requerido');
                return;
            }

            const payload = {
                userId: this.datosCotizacion.userId,
                clientType: this.datosCotizacion.tipoCliente,
                clientName: this.datosCotizacion.nombreCliente,
                rate: this.datosCotizacion.tasa,
                currency: this.datosCotizacion.moneda,
                equipment: this.datosCotizacion.equipos,
                totals: {
                    contract: this.datosCotizacion.totales?.contrato || 0,
                    utility: this.datosCotizacion.totales?.utilidad || 0
                },
                // Campos adicionales para recalcular la cotización
                plazoGlobal: this.datosCotizacion.plazoGlobal,
                residualGlobal: this.datosCotizacion.residualGlobal,
                comision: this.datosCotizacion.comision,
                anticipo: this.datosCotizacion.anticipo || 0
            };

            console.log('📤 Payload para guardar_final.php:', JSON.stringify(payload, null, 2));

            const respuesta = await fetch('api/guardar_final.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            if (!respuesta.ok) {
                throw new Error(`Error HTTP: ${respuesta.status}`);
            }

            const datos = await respuesta.json();
            console.log('📄 Respuesta del servidor (final):', datos);
            
            if (datos.status === 'success') {
                this.idCotizacionActual = datos.data?.quoteId || datos.quoteId;
                console.log('✅ Cotización final guardada con ID:', this.idCotizacionActual);
                return datos;
            } else {
                throw new Error(datos.message || 'Error desconocido al guardar cotización final');
            }
        } catch (error) {
            console.error('❌ Error al guardar cotización final:', error);
            this.mostrarError('Error al guardar la cotización final: ' + error.message);
            throw error;
        }
    }

    nuevaCotizacion() {
        // Reiniciar todo el asistente
        this.limpiarCotizacion();
        
        // Mostrar footer de navegación al iniciar nueva cotización
        const navigationFooter = document.querySelector('footer');
        if (navigationFooter) navigationFooter.style.display = 'block';
        
        this.mostrarFase(0);
        
        // Recargar catálogos si es necesario
        this.cargarCatalogos();
    }

    // Método para actualizar la información en el footer
    actualizarFooterInfo() {
        const tasaFooter = document.getElementById('tasa-footer');
        const plazoFooter = document.getElementById('plazo-footer');
        const residualFooter = document.getElementById('residual-footer');

        const tasaValor = document.getElementById('tasa-valor');
        const plazoValor = document.getElementById('plazo-valor');
        const residualValor = document.getElementById('residual-valor');

        // Solo mostrar tasa para vendedores/admin, no para clientes
        if (window.CURRENT_USER && window.CURRENT_USER.role === 'client') {
            // Para clientes, ocultar tasa
            if (tasaFooter) tasaFooter.classList.add('hidden');
        } else {
            // Para vendedores/admin, mostrar tasa anual
            if (this.datosCotizacion.tasa > 0) {
                const tasaAnual = (this.datosCotizacion.tasa * 12 * 100).toFixed(2);
                if (tasaValor) tasaValor.textContent = `${tasaAnual}%`;
                if (tasaFooter) tasaFooter.classList.remove('hidden');
            } else {
                if (tasaFooter) tasaFooter.classList.add('hidden');
            }
        }

        // Actualizar plazo
        if (this.datosCotizacion.plazoGlobal > 0) {
            if (plazoValor) plazoValor.textContent = `${this.datosCotizacion.plazoGlobal} meses`;
            if (plazoFooter) plazoFooter.classList.remove('hidden');
        } else {
            if (plazoFooter) plazoFooter.classList.add('hidden');
        }

        // Actualizar residual
        if (this.datosCotizacion.residualGlobal > 0) {
            if (residualValor) residualValor.textContent = `${this.datosCotizacion.residualGlobal}%`;
            if (residualFooter) residualFooter.classList.remove('hidden');
        } else {
            if (residualFooter) residualFooter.classList.add('hidden');
        }
    }
    
    // Métodos para enlaces compartidos
    async generarEnlaceCompartido() {
        if (!this.idCotizacionActual) {
            this.mostrarError('No hay cotización para generar enlace');
            return;
        }
        
        try {
            // Mostrar loading
            document.getElementById('loading-enlace').classList.remove('hidden');
            document.getElementById('btn-generar-enlace').classList.add('hidden');
            
            const response = await fetch('api/generar_enlace_compartido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    cotizacion_id: this.idCotizacionActual
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Mostrar QR
                if (data.data.qr_disponible && data.data.qr_url) {
                    document.getElementById('imagen-qr').src = data.data.qr_url;
                    document.getElementById('folio-compartido').textContent = data.data.folio;
                    document.getElementById('contenedor-qr').classList.remove('hidden');
                }
                
                // Mostrar enlace
                if (data.data.url_publica) {
                    document.getElementById('enlace-publico').value = data.data.url_publica;
                    document.getElementById('contenedor-enlace').classList.remove('hidden');
                }
                
                this.mostrarExito('Enlace compartido generado exitosamente');
            } else {
                this.mostrarError(data.message || 'Error al generar enlace compartido');
                document.getElementById('btn-generar-enlace').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error al generar enlace compartido:', error);
            let errorMessage = 'Error al generar enlace';
            
            if (error.message.includes('HTTP error')) {
                errorMessage = `Error del servidor: ${error.message}`;
            } else if (error.name === 'SyntaxError') {
                errorMessage = 'Error en la respuesta del servidor';
            } else {
                errorMessage = error.message || 'Error de conexión al generar enlace';
            }
            
            this.mostrarError(errorMessage);
            document.getElementById('btn-generar-enlace').classList.remove('hidden');
        } finally {
            document.getElementById('loading-enlace').classList.add('hidden');
        }
    }
    
    async copiarEnlace() {
        const enlace = document.getElementById('enlace-publico').value;
        
        if (!enlace) {
            this.mostrarError('No hay enlace para copiar');
            return;
        }
        
        try {
            await navigator.clipboard.writeText(enlace);
            
            // Feedback visual
            const btn = document.getElementById('btn-copiar-enlace');
            const textoOriginal = btn.textContent;
            btn.textContent = '¡Copiado!';
            btn.className = btn.className.replace('text-blue-600', 'text-green-600');
            
            setTimeout(() => {
                btn.textContent = textoOriginal;
                btn.className = btn.className.replace('text-green-600', 'text-blue-600');
            }, 2000);
            
        } catch (error) {
            console.error('Error al copiar enlace:', error);
            // Fallback para navegadores que no soportan clipboard API
            const enlaceInput = document.getElementById('enlace-publico');
            enlaceInput.select();
            document.execCommand('copy');
            this.mostrarExito('Enlace copiado al portapapeles');
        }
    }

    // Métodos para envío por email
    configurarSeccionEmail() {
        const seccionEmail = document.getElementById('seccion-envio-email');
        const contenedorEmailDestino = document.getElementById('contenedor-email-destino');
        const infoEmailCliente = document.getElementById('info-email-cliente');
        const emailUsuarioActual = document.getElementById('email-usuario-actual');
        
        if (!seccionEmail) return;
        
        // Mostrar sección de email
        seccionEmail.classList.remove('hidden');
        
        // Configurar según el rol del usuario
        const userRole = window.CURRENT_USER?.role;
        const userEmail = window.CURRENT_USER?.email;
        
        if (userRole === 'client' || userRole === 'cliente') {
            // Para clientes: mostrar info con su email
            infoEmailCliente.classList.remove('hidden');
            contenedorEmailDestino.classList.add('hidden');
            if (emailUsuarioActual && userEmail) {
                emailUsuarioActual.textContent = userEmail;
            }
        } else {
            // Para admin/vendor: mostrar campo de input
            contenedorEmailDestino.classList.remove('hidden');
            infoEmailCliente.classList.add('hidden');
        }
    }
    
    async enviarCotizacionPorEmail() {
        if (!this.idCotizacionActual) {
            this.mostrarError('No hay cotización para enviar');
            return;
        }
        
        const userRole = window.CURRENT_USER?.role;
        let emailDestino = null;
        
        // Validar email según el rol
        if (userRole === 'client' || userRole === 'cliente') {
            emailDestino = window.CURRENT_USER?.email;
            if (!emailDestino) {
                this.mostrarError('No se encontró tu email registrado');
                return;
            }
        } else {
            // Para admin/vendor, obtener email del campo
            const inputEmail = document.getElementById('email-destino');
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
        
        try {
            // Mostrar loading
            document.getElementById('loading-email').classList.remove('hidden');
            document.getElementById('btn-enviar-email').classList.add('hidden');
            
            const response = await fetch('api/enviar_cotizacion_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    cotizacion_id: this.idCotizacionActual,
                    email_destino: emailDestino
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.mostrarExito(`Email enviado exitosamente a ${emailDestino}`);
            } else {
                this.mostrarError(data.message || 'Error al enviar email');
                document.getElementById('btn-enviar-email').classList.remove('hidden');
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
            document.getElementById('btn-enviar-email').classList.remove('hidden');
        } finally {
            document.getElementById('loading-email').classList.add('hidden');
        }
    }
    
    validarEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
}

// Inicializar el controlador inmediatamente
// console.log('🚀 Inicializando ControladorAsistente...');

// Función para inicializar
function inicializarAsistente() {
    if (!window.asistente) {
        // console.log('📝 Creando nueva instancia de ControladorAsistente...');
        window.asistente = new ControladorAsistente();
        // console.log('✅ window.asistente creado:', window.asistente);
    }
}

// Inicializar inmediatamente si el DOM ya está listo
if (document.readyState === 'loading') {
    // console.log('⏳ DOM aún cargando, esperando DOMContentLoaded...');
    document.addEventListener('DOMContentLoaded', inicializarAsistente);
} else {
    // console.log('✅ DOM ya está listo, inicializando inmediatamente...');
    inicializarAsistente();
}