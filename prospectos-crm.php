<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

iniciarSesion();

if (!isLoggedIn() && isset($_GET['preview']) && $_GET['preview'] === '1') {
    $_SESSION['user_id'] = 0;
    $_SESSION['username'] = 'preview';
    $_SESSION['full_name'] = 'Vista previa';
    $_SESSION['email'] = 'preview@example.com';
    $_SESSION['role'] = 'admin';
    $_SESSION['login_time'] = time();
    $_SESSION['expires'] = time() + (24 * 60 * 60);
}

requireLogin();

$summaryCards = [
    [
        'title' => 'Total de Prospectos',
        'value' => '10',
        'subtitle' => 'Gestiona prospectos potenciales y rastrea conversiones',
    ],
    [
        'title' => 'Nuevos Prospectos',
        'value' => '2',
        'subtitle' => 'Nuevos prospectos capturados esta semana',
    ],
    [
        'title' => 'Tasa de Conversión',
        'value' => '30%',
        'subtitle' => 'Prospectos convertidos a clientes este mes',
    ],
];

$prospectos = [
    [
        'nombre' => 'Lisette Rocha',
        'empresa' => 'Unite Media',
        'contacto' => 'lisette@unitemedia.com',
        'telefono' => '+52 55 1234 5678',
        'canal' => 'Social',
        'etapa' => 'Nuevo',
        'responsable' => 'Giovanny Gutierrez',
        'fecha' => 'Sep 16, 2025',
        'etapaColor' => 'bg-sky-100 text-sky-600',
    ],
    [
        'nombre' => 'Ariah Schneider',
        'empresa' => 'Ad Advertisement',
        'contacto' => 'ariah@adadvertisement.com',
        'telefono' => '+52 55 9876 5432',
        'canal' => 'Social',
        'etapa' => 'Contactado',
        'responsable' => 'Winfred Roux',
        'fecha' => 'Sep 13, 2025',
        'etapaColor' => 'bg-emerald-100 text-emerald-600',
    ],
    [
        'nombre' => 'Zoe Chávez',
        'empresa' => 'Brightwood',
        'contacto' => 'zoe@brightwood.io',
        'telefono' => '+52 81 4596 8745',
        'canal' => 'Email',
        'etapa' => 'En seguimiento',
        'responsable' => 'Charles Harris',
        'fecha' => 'Sep 10, 2025',
        'etapaColor' => 'bg-amber-100 text-amber-600',
    ],
    [
        'nombre' => 'Chanel Bowers',
        'empresa' => 'Blue Gate',
        'contacto' => 'chanel@bluegate.co',
        'telefono' => '+52 55 6754 2390',
        'canal' => 'Referral',
        'etapa' => 'Completado',
        'responsable' => 'Marley Wolff',
        'fecha' => 'Sep 09, 2025',
        'etapaColor' => 'bg-violet-100 text-violet-600',
    ],
    [
        'nombre' => 'Ariya Black',
        'empresa' => 'Luminet',
        'contacto' => 'ariya@luminet.com',
        'telefono' => '+52 55 2198 6437',
        'canal' => 'Email',
        'etapa' => 'Nuevo',
        'responsable' => 'Fionna Sanford',
        'fecha' => 'Sep 08, 2025',
        'etapaColor' => 'bg-sky-100 text-sky-600',
    ],
    [
        'nombre' => 'Rylann Hollowell',
        'empresa' => 'NextDoor',
        'contacto' => 'rylann@nextdoor.mx',
        'telefono' => '+52 81 8765 1243',
        'canal' => 'Social',
        'etapa' => 'En seguimiento',
        'responsable' => 'Charles Harris',
        'fecha' => 'Sep 08, 2025',
        'etapaColor' => 'bg-amber-100 text-amber-600',
    ],
    [
        'nombre' => 'Payton Hollis',
        'empresa' => 'Sparkflow',
        'contacto' => 'payton@sparkflow.com',
        'telefono' => '+52 55 8764 2312',
        'canal' => 'Web',
        'etapa' => 'Contactado',
        'responsable' => 'Kian Guevara',
        'fecha' => 'Sep 05, 2025',
        'etapaColor' => 'bg-emerald-100 text-emerald-600',
    ],
    [
        'nombre' => 'Aiyla Michael',
        'empresa' => 'Northbound',
        'contacto' => 'aiyla@northbound.org',
        'telefono' => '+52 55 5432 1987',
        'canal' => 'Web',
        'etapa' => 'Completado',
        'responsable' => 'Fionna Sanford',
        'fecha' => 'Sep 04, 2025',
        'etapaColor' => 'bg-violet-100 text-violet-600',
    ],
    [
        'nombre' => 'Zayn Delaney',
        'empresa' => 'Peakforce',
        'contacto' => 'zayn@peakforce.com',
        'telefono' => '+52 33 8765 1209',
        'canal' => 'Social',
        'etapa' => 'En seguimiento',
        'responsable' => 'Fionna Sanford',
        'fecha' => 'Sep 03, 2025',
        'etapaColor' => 'bg-amber-100 text-amber-600',
    ],
    [
        'nombre' => 'Ella Mcdaniel',
        'empresa' => 'Growthlab',
        'contacto' => 'ella@growthlab.io',
        'telefono' => '+52 55 7865 9021',
        'canal' => 'Email',
        'etapa' => 'Nuevo',
        'responsable' => 'Yaretzi Aguirre',
        'fecha' => 'Sep 01, 2025',
        'etapaColor' => 'bg-sky-100 text-sky-600',
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospectos y CRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        background: '#f6f7fb',
                        border: '#e5e7eb',
                        dark: '#111827',
                        muted: '#6b7280',
                        primary: '#2563eb',
                        success: '#10b981',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .shadow-soft { box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .shadow-card { box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06); }
        .table-shadow { box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
        .button-shadow { box-shadow: 0 12px 30px rgba(37, 99, 235, 0.25); }
    </style>
</head>
<body class="min-h-screen bg-[#f6f7fb]">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 flex-col border-r border-gray-200 bg-white px-6 py-8 shadow-soft lg:flex">
            <div class="mb-10">
                <div class="text-2xl font-bold text-primary">Wiser CRM</div>
                <p class="mt-1 text-sm text-gray-500">Panel de demostración</p>
            </div>
            <nav class="flex-1 space-y-6 text-sm font-medium text-gray-600">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">General</p>
                    <div class="mt-3 space-y-2">
                        <a href="#" class="flex items-center gap-3 rounded-xl bg-primary/10 px-4 py-2 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
                            </svg>
                            Panel principal
                        </a>
                        <a href="#" class="flex items-center gap-3 rounded-xl px-4 py-2 transition hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75zm0 5.25h.008v.008H3.75V12zm0 5.25h.008v.008H3.75v-.008z" />
                            </svg>
                            Prospectos
                        </a>
                        <a href="#" class="flex items-center gap-3 rounded-xl px-4 py-2 transition hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v12m-9-12v12" />
                            </svg>
                            Pipeline
                        </a>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">CRM</p>
                    <div class="mt-3 space-y-2">
                        <a href="#" class="flex items-center gap-3 rounded-xl px-4 py-2 transition hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5a7.5 7.5 0 1115 0" />
                            </svg>
                            Clientes
                        </a>
                        <a href="#" class="flex items-center gap-3 rounded-xl px-4 py-2 transition hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5M3.75 19.5h16.5M4.5 8.25h15M4.5 15.75h15" />
                            </svg>
                            Actividades
                        </a>
                        <a href="#" class="flex items-center gap-3 rounded-xl px-4 py-2 transition hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Reportes
                        </a>
                    </div>
                </div>
            </nav>
            <div class="mt-10 rounded-2xl bg-gray-50 p-4">
                <p class="text-sm font-semibold text-gray-900">Próximo evento</p>
                <p class="mt-1 text-xs text-gray-500">Reunión de seguimiento - 12:00 PM</p>
            </div>
        </aside>
        <div class="flex-1">
            <div class="border-b border-gray-200 bg-white px-4 py-4 shadow-soft lg:hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Wiser CRM</p>
                        <p class="text-base font-semibold text-gray-900">Menú principal</p>
                    </div>
                    <button class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">
                        Menú
                    </button>
                </div>
            </div>
            <div class="min-h-screen px-4 py-10 sm:px-8 lg:px-16 xl:px-24">
                <div class="mx-auto max-w-7xl space-y-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wider text-primary">Gestión de prospectos y CRM</p>
                    <h1 class="mt-2 text-3xl font-bold text-gray-900 sm:text-4xl">Prospectos y CRM</h1>
                    <p class="mt-2 text-base text-gray-500">Gestiona prospectos potenciales y rastrea conversiones</p>
                </div>
                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <button class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 shadow-card transition hover:-translate-y-0.5 hover:shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                        </svg>
                        Actualizar
                    </button>
                    <a href="#" class="button-shadow flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Agregar Prospecto
                    </a>
                </div>
            </header>

            <section class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($summaryCards as $card): ?>
                    <article class="rounded-2xl bg-white p-6 shadow-card transition hover:-translate-y-0.5 hover:shadow-lg">
                        <h2 class="text-sm font-semibold text-gray-500"><?php echo htmlspecialchars($card['title']); ?></h2>
                        <p class="mt-4 text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($card['value']); ?></p>
                        <p class="mt-3 text-sm text-gray-500"><?php echo htmlspecialchars($card['subtitle']); ?></p>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="rounded-3xl bg-white p-6 shadow-soft">
                <header class="flex flex-col gap-4 border-b border-gray-100 pb-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Gestión de Prospectos (10)</h2>
                        <p class="mt-1 text-sm text-gray-500">Monitorea el estado y rendimiento de tus embudos de conversión</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative">
                            <select class="w-full appearance-none rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option>Todos los Prospectos</option>
                                <option>Nuevos</option>
                                <option>En seguimiento</option>
                                <option>Completados</option>
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 10.5l3.75 3.75 3.75-3.75" />
                            </svg>
                        </div>
                        <div class="relative">
                            <select class="w-full appearance-none rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option>Ordenar por: Fecha de registro</option>
                                <option>Ordenar por: Nombre</option>
                                <option>Ordenar por: Etapa</option>
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 10.5l3.75 3.75 3.75-3.75" />
                            </svg>
                        </div>
                    </div>
                </header>

                <div class="mt-6 overflow-x-auto">
                    <table class="w-full min-w-[960px] table-auto border-collapse">
                        <thead class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                            <tr>
                                <th class="pb-4 pl-4 pr-4">Prospecto</th>
                                <th class="pb-4 pr-4">Información de Contacto</th>
                                <th class="pb-4 pr-4">Canal</th>
                                <th class="pb-4 pr-4">Etapa</th>
                                <th class="pb-4 pr-4">Responsable</th>
                                <th class="pb-4 pr-4">Fecha de Registro</th>
                                <th class="pb-4 pr-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm text-gray-600">
                            <?php foreach ($prospectos as $prospecto): ?>
                                <tr class="transition hover:bg-gray-50/70">
                                    <td class="py-4 pl-4 pr-4">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($prospecto['nombre']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($prospecto['empresa']); ?></div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <div class="flex flex-col space-y-1">
                                            <span><?php echo htmlspecialchars($prospecto['contacto']); ?></span>
                                            <span class="text-xs text-gray-400"><?php echo htmlspecialchars($prospecto['telefono']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                            <?php echo htmlspecialchars($prospecto['canal']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo $prospecto['etapaColor']; ?>">
                                            <?php echo htmlspecialchars($prospecto['etapa']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 pr-4 text-gray-900">
                                        <?php echo htmlspecialchars($prospecto['responsable']); ?>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <?php echo htmlspecialchars($prospecto['fecha']); ?>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition hover:-translate-y-0.5 hover:border-primary/30 hover:text-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                                                </svg>
                                            </button>
                                            <button class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition hover:-translate-y-0.5 hover:border-primary/30 hover:text-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </button>
                                            <button class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition hover:-translate-y-0.5 hover:border-rose-200 hover:text-rose-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
