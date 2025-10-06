// Utilidades para mejorar la experiencia en desktop
// Mantiene enfoque mobile-first

class DesktopUtils {
    constructor() {
        this.isDesktop = window.innerWidth >= 1024;
        this.init();
    }

    init() {
        this.detectDevice();
        this.setupEventListeners();
        this.enhanceDesktopExperience();
    }

    detectDevice() {
        // Detectar si es desktop y ajustar accordingly
        const updateDevice = () => {
            this.isDesktop = window.innerWidth >= 1024;
            document.body.classList.toggle('is-desktop', this.isDesktop);
            document.body.classList.toggle('is-mobile', !this.isDesktop);
        };

        updateDevice();
        window.addEventListener('resize', updateDevice);
    }

    setupEventListeners() {
        // Mejorar navegación con teclado en desktop
        if (this.isDesktop) {
            document.addEventListener('keydown', this.handleKeyboardShortcuts.bind(this));
        }

        // Mejorar hover effects en desktop
        document.addEventListener('mouseover', this.handleMouseHover.bind(this));
    }

    handleKeyboardShortcuts(e) {
        // Solo en desktop
        if (!this.isDesktop) return;

        // ESC para cerrar modales/carrito
        if (e.key === 'Escape') {
            if (window.controladorCarrito && window.controladorCarrito.estaAbierto) {
                window.controladorCarrito.cerrarCarrito();
            }
        }

        // Ctrl/Cmd + S para guardar
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const saveButton = document.querySelector('[data-action="save"], .btn-save, #btn-guardar');
            if (saveButton && !saveButton.disabled) {
                saveButton.click();
            }
        }
    }

    handleMouseHover(e) {
        // Agregar efectos hover sutiles a elementos interactivos en desktop
        if (!this.isDesktop) return;

        const element = e.target;
        if (element.matches('.elemento-equipo, .equipment-item')) {
            element.classList.add('hover-elevate');
        }
    }

    enhanceDesktopExperience() {
        if (!this.isDesktop) return;

        // Mejorar el carrito en desktop
        this.enhanceCartForDesktop();

        // Mejorar formularios en desktop
        this.enhanceFormsForDesktop();

        // Mejorar tablas en desktop
        this.enhanceTablesForDesktop();
    }

    enhanceCartForDesktop() {
        const cartPanel = document.getElementById('panel-carrito');
        if (cartPanel) {
            // El carrito ya está optimizado via CSS para desktop
            // Aquí podemos agregar funcionalidad adicional si es necesaria
        }
    }

    enhanceFormsForDesktop() {
        // Buscar formularios que podrían beneficiarse del layout en grid
        const forms = document.querySelectorAll('form, .form-container');
        
        forms.forEach(form => {
            // Buscar grupos de inputs que podrían ir en columnas
            const inputGroups = form.querySelectorAll('.input-group, .form-group, .mb-4');
            
            if (inputGroups.length >= 3) {
                // Si hay 3 o más grupos, considerar layout en grid
                const container = document.createElement('div');
                container.className = 'form-grid-desktop';
                
                // Envolver grupos en contenedor grid solo en desktop
                if (inputGroups.length <= 6) {
                    form.classList.add('desktop-enhanced');
                }
            }
        });
    }

    enhanceTablesForDesktop() {
        // Convertir listas de cards a tablas en desktop cuando sea apropiado
        const cardLists = document.querySelectorAll('.lista-cotizaciones-cards, .equipment-list');
        
        cardLists.forEach(list => {
            if (list.children.length > 0) {
                list.classList.add('desktop-table-enhanced');
            }
        });
    }

    // Método para optimizar animaciones según el dispositivo
    optimizeAnimations() {
        if (this.isDesktop) {
            // En desktop podemos permitir animaciones más elaboradas
            document.documentElement.style.setProperty('--animation-duration', '300ms');
        } else {
            // En móvil mantener animaciones rápidas
            document.documentElement.style.setProperty('--animation-duration', '200ms');
        }
    }

    // Método para ajustar espaciado según el tamaño de pantalla
    adjustSpacing() {
        const containers = document.querySelectorAll('.responsive-container');
        
        containers.forEach(container => {
            if (this.isDesktop) {
                container.classList.add('desktop-spacing');
            } else {
                container.classList.remove('desktop-spacing');
            }
        });
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.desktopUtils = new DesktopUtils();
    });
} else {
    window.desktopUtils = new DesktopUtils();
}

// Exportar para uso en otros scripts si es necesario
window.DesktopUtils = DesktopUtils;