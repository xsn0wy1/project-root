<?php
declare(strict_types=1);

// Explicit, local-only presentation data. Setup and migration never call this file.
// People, accounts, identifiers and reports are invented. Only facilities are real.
// Saves use the existing domain functions; PDF uploads use the unchanged HTTP API.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/autoload.php';

function demo_facilities(): array
{
    // WGS84 centres of the named OSM hospital features, checked against official addresses.
    // OSM contributors: https://www.openstreetmap.org/copyright (ODbL).
    // Each osm_way can be inspected at https://www.openstreetmap.org/way/NUMBER.
    return [
        [
            'name' => 'Hospital de Clínicas',
            'address' => 'Avenida Italia 2870',
            'latitude' => -34.8915346,
            'longitude' => -56.1521338,
            'osm_way' => 37514653,
            'source' => 'https://www.cposgrados.fmed.edu.uy/node/330'
        ],
        [
            'name' => 'Hospital Maciel',
            'address' => '25 de Mayo 174',
            'latitude' => -34.9083467,
            'longitude' => -56.2118677,
            'osm_way' => 905392238,
            'source' => 'https://www.gub.uy/tramites/servicio-medicina-paliativa-hospital-maciel'
        ],
        [
            'name' => 'Hospital Pasteur',
            'address' => 'Larravide 2458',
            'latitude' => -34.8750852,
            'longitude' => -56.1384209,
            'osm_way' => 180467229,
            'source' => 'https://www.asse.com.uy/contenido/Hospital-Pasteur-5213'
        ],
        [
            'name' => 'Hospital Pereira Rossell',
            'address' => 'Bulevar Artigas 1550',
            'latitude' => -34.8986503,
            'longitude' => -56.1628384,
            'osm_way' => 156270380,
            'source' => 'https://pereirarossell.gub.uy/'
        ],
        [
            'name' => 'Hospital Español',
            'address' => 'Avenida Garibaldi 1729',
            'latitude' => -34.8750288,
            'longitude' => -56.1814031,
            'osm_way' => 251822831,
            'source' => 'https://www.descubrimontevideo.uy/hospital-espanol'
        ],
        [
            'name' => 'Centro Hospitalario Saint Bois',
            'address' => 'Camino Fauquet 6358',
            'latitude' => -34.7882944,
            'longitude' => -56.2411702,
            'osm_way' => 270163664,
            'source' => 'https://www.descubrimontevideo.uy/hospital-saint-bois'
        ]
    ];
}

function demo_require_local_database(array $config): void
{
    $name = $config['db']['name'];
    $localName = preg_match('/(?:^|[_-])(local|dev|development|demo|test|refactor|presentation)(?:[_-]|$)/i', $name);
    $productionName = preg_match('/(?:^|[_-])(prod|production|live)(?:[_-]|$)/i', $name);
    $localHost = in_array($config['db']['host'], ['localhost', '127.0.0.1', '::1'], true);
    if (!$localName || $productionName || !$localHost) {
        throw new RuntimeException('Solo se permite una base local/dev identificada como local, dev, demo, test o refactor.');
    }
    if (!$config['route_mapping_enabled'] || !$config['router_url'] || !$config['geocoder_url']) {
        throw new RuntimeException('route_mapping_enabled debe estar activo y los endpoints HTTPS deben estar configurados.');
    }
}

function demo_main_admin(PDO $db): array
{
    $admin = $db->query(
        "SELECT u.* FROM users u JOIN user_roles ur ON ur.user_id=u.id "
        . "JOIN roles r ON r.id=ur.role_id JOIN staff s ON s.id=u.staff_id "
        . "WHERE r.code='admin' AND u.status='active' AND s.status='active' ORDER BY u.id LIMIT 1"
    )->fetch();
    if (!$admin) {
        throw new RuntimeException('Primero creá la cuenta administradora principal con users.php create-admin.');
    }
    return $admin;
}

function demo_router_preflight(PDO $db, array $config, array $facilities): array
{
    $paths = [];
    // Roll back cache changes on both success and failure: failed preflight keeps all records intact.
    // Clearing only these keys forces a live request, rather than accepting old fixture geometry.
    $db->beginTransaction();
    try {
        foreach ($facilities as $index => $origin) {
            $destination = $facilities[($index + 1) % count($facilities)];
            $coordinates = $origin['longitude'] . ',' . $origin['latitude']
                . ';' . $destination['longitude'] . ',' . $destination['latitude'];
            $url = rtrim($config['router_url'], '/') . '/' . $coordinates
                . '?overview=full&geometries=geojson&steps=false';
            $cacheKey = hash('sha256', $url);
            $statement = $db->prepare('DELETE FROM route_map_cache WHERE cache_key=?');
            $statement->execute([$cacheKey]);
            $response = route_mapping_cached($db, $url, microtime(true) + 12);
            $path = route_mapping_response($response);
            if ($path === null || count(json_decode($path['geometry'], true, 64, JSON_THROW_ON_ERROR)) <= 20) {
                throw new RuntimeException('El router no respondió con una ruta de más de 20 puntos: ' . $origin['name'] . '. No se borraron los registros.');
            }
            $paths[] = [
                'cache_key' => $cacheKey,
                'payload' => json_encode($response, JSON_THROW_ON_ERROR),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 2592000),
                'distance_meters' => $path['distance_meters'],
                'duration_seconds' => $path['duration_seconds']
            ];
        }
    } finally {
        $db->rollBack();
    }
    return $paths;
}

function demo_private_directory(array $config): string
{
    $directory = dirname(__DIR__, 3) . '/tests/.runtime/demo-data/' . $config['db']['name'];
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear el directorio privado de la demostración.');
    }
    chmod($directory, 0700);
    return $directory;
}

function demo_reset(PDO $db, array $config, array $admin): void
{
    $storage = new Hospital\Storage\PrivateStorage($config);
    $files = [];
    foreach ($db->query('SELECT storage_key,purpose FROM stored_files')->fetchAll() as $file) {
        if ($file['purpose'] === 'avatar') {
            $files[] = $storage->path('users', $file['storage_key'], 'png');
        } elseif ($file['purpose'] === 'document') {
            $files[] = $storage->path('documents', $file['storage_key']);
        } else {
            throw new RuntimeException('Hay un tipo de archivo privado desconocido; revisalo antes de borrar.');
        }
    }
    // Move files aside first. A failed SQL reset restores them; committed deletions remove them.
    $quarantine = $storage->directory('quarantine', true);
    $moved = [];
    $committed = false;
    try {
        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }
            $temporary = $quarantine . '/' . bin2hex(random_bytes(32)) . '.bin';
            if (!rename($path, $temporary)) {
                throw new RuntimeException('No se pudo apartar un archivo privado para el reinicio.');
            }
            $moved[$path] = $temporary;
        }
        $db->beginTransaction();
        $db->exec('UPDATE user_settings SET avatar_file_id=NULL,default_facility_id=NULL');
        $db->exec('UPDATE simulation_state SET current_run_id=NULL,version=version+1 WHERE id=1');
        $tables = [
            'document_access_links', 'document_versions', 'documents',
            'survey_answers', 'survey_responses', 'survey_links', 'survey_items', 'surveys',
            'transfer_latest_positions', 'transfer_positions', 'transfer_events',
            'resource_reservations', 'transfer_crew', 'transfer_drafts', 'transfers',
            'simulation_runs', 'route_stops', 'route_templates', 'ambulances',
            'patient_appointments', 'patient_contacts', 'patient_encounters', 'patients',
            'doctor_profiles', 'personnel_profiles', 'staff_roles', 'staff_specialties',
            'user_notifications', 'user_diagnostics', 'user_sessions', 'password_reset_tokens',
            'auth_rate_limits', 'audit_events', 'stored_files', 'facilities',
            'specialties', 'departments', 'route_map_cache'
        ];
        foreach ($tables as $table) {
            $db->exec('DELETE FROM ' . $table);
        }
        $statement = $db->prepare('DELETE FROM users WHERE id<>?');
        $statement->execute([$admin['id']]);
        $statement = $db->prepare('DELETE FROM staff WHERE id<>?');
        $statement->execute([$admin['staff_id']]);
        $db->commit();
        $committed = true;
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($moved as $path => $temporary) {
            if ($committed) {
                if (!unlink($temporary)) {
                    throw new RuntimeException('El reinicio terminó, pero no se pudo eliminar un archivo apartado.');
                }
            } elseif (!rename($temporary, $path)) {
                throw new RuntimeException('No se pudo restaurar un archivo después de cancelar el reinicio.');
            }
        }
    }
}

function demo_actor(PDO $db, string $id): void
{
    $user = users_by_id($db, $id);
    $_SESSION = ['user_id' => $id, 'auth_version' => (int)$user['auth_version']];
}

function demo_identity(int $number): string
{
    // Invented future-range CIs, formatted with the ordinary Uruguayan check digit.
    $base = (string)$number;
    $weights = [2, 9, 8, 7, 6, 3, 4];
    $sum = 0;
    foreach ($weights as $index => $weight) {
        $sum += (int)$base[$index] * $weight;
    }
    return $base . ((10 - $sum % 10) % 10);
}

function demo_accounts(PDO $db, array $config, array $admin, string $privateDirectory): array
{
    $definitions = [
        ['username' => 'coordinacion.traslados', 'name' => 'Natalia Costa Barrios', 'role' => 'dispatcher'],
        ['username' => 'archivo.clinico', 'name' => 'Diego Ferrer Luz', 'role' => 'records_manager']
    ];
    $accounts = [];
    foreach ($definitions as $index => $definition) {
        $password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $_SESSION = [];
        $registration = auth_register($db, $config, [
            'username' => $definition['username'],
            'email' => $definition['username'] . '@hospital.example',
            'national_id' => demo_identity(9901001 + $index),
            'password' => $password
        ]);
        $id = $registration['user_id'];
        $user = users_by_id($db, $id);
        demo_actor($db, (string)$admin['id']);
        accounts_manage($db, $id, (int)$user['version'], 'approve', [$definition['role']], (string)$admin['id'], $GLOBALS['request_id']);
        demo_actor($db, $id);
        settings_profile($db, $id, [
            'version' => users_settings($db, $id)['version'],
            'full_name' => $definition['name']
        ]);
        $accounts[$definition['role']] = [
            'id' => $id,
            'username' => $definition['username'],
            'password' => $password
        ];
    }
    $path = $privateDirectory . '/credentials.json';
    $handle = fopen($path, 'c');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir el archivo privado de contraseñas.');
    }
    try {
        chmod($path, 0600);
        ftruncate($handle, 0);
        $bytes = json_encode(['database' => $config['db']['name'], 'accounts' => $accounts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($handle, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('No se pudieron escribir las contraseñas privadas.');
        }
    } finally {
        fclose($handle);
    }
    return $accounts;
}

function demo_save_facilities(PDO $db, array $config, string $actor, array $definitions): array
{
    demo_actor($db, $actor);
    $facilities = [];
    foreach ($definitions as $definition) {
        $facility = destinations_save($db, $config, $actor, [
            'name' => $definition['name'],
            'address' => $definition['address'],
            'locality' => 'Montevideo',
            'facility_type' => 'hospital',
            'phone' => '',
            'notes' => 'Centro real de Montevideo. Los traslados de esta presentación son ficticios.',
            'availability' => 'active'
        ]);
        // The destination form has no coordinate fields; retain the verified WGS84 metadata directly.
        $statement = $db->prepare('UPDATE facilities SET latitude=?,longitude=? WHERE id=?');
        $statement->execute([$definition['latitude'], $definition['longitude'], $facility['id']]);
        $facilities[] = array_merge($definition, ['id' => $facility['id']]);
    }
    return $facilities;
}

function demo_doctors(PDO $db, array $config, string $actor): array
{
    $definitions = [
        ['name' => 'Camila Arce Lemos', 'title' => 'Dra.', 'specialty' => 'Medicina interna', 'department' => 'Medicina', 'shift' => 'morning'],
        ['name' => 'Joaquín Viera Soto', 'title' => 'Dr.', 'specialty' => 'Cardiología', 'department' => 'Cardiología', 'shift' => 'afternoon'],
        ['name' => 'Martina Cabrera Vidal', 'title' => 'Dra.', 'specialty' => 'Pediatría', 'department' => 'Pediatría', 'shift' => 'morning'],
        ['name' => 'Bruno Méndez Rial', 'title' => 'Dr.', 'specialty' => 'Traumatología', 'department' => 'Traumatología', 'shift' => 'afternoon'],
        ['name' => 'Florencia Leal Acosta', 'title' => 'Dra.', 'specialty' => 'Neurología', 'department' => 'Neurología', 'shift' => 'morning'],
        ['name' => 'Santiago Ríos Otero', 'title' => 'Dr.', 'specialty' => 'Cirugía general', 'department' => 'Cirugía', 'shift' => 'afternoon'],
        ['name' => 'Agustina Paz Moreira', 'title' => 'Dra.', 'specialty' => 'Medicina de emergencia', 'department' => 'Emergencia', 'shift' => 'night'],
        ['name' => 'Tomás Larrosa Núñez', 'title' => 'Dr.', 'specialty' => 'Geriatría', 'department' => 'Medicina', 'shift' => 'rotating']
    ];
    demo_actor($db, $actor);
    $doctors = [];
    foreach ($definitions as $index => $definition) {
        $start = '08:00';
        $end = '14:00';
        if ($definition['shift'] === 'afternoon') {
            $start = '14:00';
            $end = '20:00';
        } elseif ($definition['shift'] === 'night') {
            $start = '20:00';
            $end = '08:00';
        }
        $doctor = doctors_save($db, $config, $actor, [
            'title' => $definition['title'],
            'full_name' => $definition['name'],
            'national_id' => demo_identity(9701001 + $index),
            'license' => 'MSP' . (48001 + $index),
            'specialty' => $definition['specialty'],
            'department' => $definition['department'],
            'office' => 'Consultorio ' . (101 + $index),
            'phone' => '091000' . (201 + $index),
            'email' => 'profesional' . ($index + 1) . '@hospital.example',
            'availability' => 'available',
            'shift' => $definition['shift'],
            'days' => 'weekdays',
            'start_time' => $start,
            'end_time' => $end,
            'notes' => 'Perfil ficticio para presentación académica.'
        ]);
        $doctors[] = array_merge($definition, ['id' => $doctor['id']]);
    }
    return $doctors;
}

function demo_patients(PDO $db, array $config, string $actor, array $doctors): array
{
    $names = [
        'Lucía Méndez Lagos', 'Mateo Silva Bentancur', 'Valentina Pereyra Sosa',
        'Nicolás Taboada Reyes', 'Sofía Duarte Píriz', 'Federico Molina Castro',
        'Julieta Rivero Peña', 'Andrés Luján Correa', 'Paula Fagúndez Vera',
        'Gabriel Olivera Sena', 'Carolina Costa Gil', 'Diego Larrosa Mesa',
        'Elena Cabrera Soler', 'Ricardo Viera Ponce', 'Teresa Acosta Lema'
    ];
    $ages = [9, 16, 21, 28, 34, 39, 44, 49, 52, 58, 63, 67, 72, 78, 84];
    $statuses = ['inpatient', 'outpatient', 'emergency', 'discharged'];
    $clinicalStates = ['Estable', 'En seguimiento', 'En observación', 'Alta médica'];
    $today = new DateTimeImmutable('today', new DateTimeZone('America/Montevideo'));
    $patients = [];
    demo_actor($db, $actor);
    foreach ($names as $index => $name) {
        $doctor = $doctors[$index % count($doctors)];
        if ($ages[$index] < 18) {
            $doctor = $doctors[2];
        }
        $sex = 'female';
        if ($index % 2 === 1) {
            $sex = 'male';
        }
        $careStatus = $statuses[$index % 4];
        $initialStatus = $careStatus;
        if ($careStatus === 'discharged') {
            $initialStatus = 'inpatient';
        }
        $input = [
            'create_token' => bin2hex(random_bytes(32)),
            'full_name' => $name,
            'national_id' => demo_identity(9601001 + $index),
            'date_of_birth' => $today->modify('-' . $ages[$index] . ' years')->format('Y-m-d'),
            'sex' => $sex,
            'age' => null,
            'phone' => '091000' . (301 + $index),
            'blood_group' => ['O+', 'A+', 'B+', 'AB+', 'O-'][$index % 5],
            'allergies' => 'Sin alergias registradas',
            'emergency_name' => 'María Lagos Olivera',
            'emergency_phone' => '091000' . (401 + $index),
            'emergency_relationship' => 'Familiar',
            'care_status' => $initialStatus,
            'room' => 'Sala ' . (201 + $index),
            'department' => $doctor['department'],
            'doctor_staff_id' => $doctor['id'],
            'doctor_specialty' => $doctor['specialty'],
            'admission_date' => $today->modify('-' . (2 + $index) . ' days')->format('Y-m-d'),
            'diagnosis' => 'Caso ficticio de seguimiento clínico y coordinación de estudios.',
            'clinical_state' => $clinicalStates[$index % 4]
        ];
        $patient = patients_create($db, $config, $actor, $input);
        if ($careStatus === 'discharged') {
            unset($input['create_token']);
            $input['version'] = $patient['version'];
            $input['care_status'] = 'discharged';
            $patient = patients_update($db, $config, $actor, $patient['id'], $input);
        }
        $patients[] = $patient;
    }
    return $patients;
}

function demo_personnel(PDO $db, array $config, string $actor, string $recordsManager): array
{
    $names = [
        'Esteban Suárez Lemos', 'Marcos Pereira Vidal', 'Rodrigo Santos Luz',
        'Alejandro Barrios Gil', 'Ignacio Píriz Otero', 'Sebastián Costa Rey',
        'Diego Ferrer Luz', 'Carolina Vidal Peña', 'Gonzalo Paz Rivero', 'Jimena Mesa Correa'
    ];
    $crew = ['drivers' => [], 'copilots' => []];
    $recordsAccount = users_by_id($db, $recordsManager);
    demo_actor($db, $actor);
    foreach ($names as $index => $name) {
        $responsibility = 'copilot';
        $license = '';
        $group = 'copilots';
        if ($index < 6) {
            $responsibility = 'driver';
            $license = 'G' . (86001 + $index);
            $group = 'drivers';
        }
        $nationalId = demo_identity(9801001 + $index);
        if ($index === 6) {
            // Link the existing fictional account through the usual shared identity lookup.
            // Its assigned transfers remain visible under the existing crew-only read scope.
            $nationalId = $recordsAccount['registration_national_id'];
        }
        $person = personnel_save($db, $config, $actor, [
            'full_name' => $name,
            'national_id' => $nationalId,
            'phone' => '091000' . (501 + $index),
            'responsibility' => $responsibility,
            'availability' => 'active',
            'driving_license' => $license
        ]);
        $crew[$group][] = $person;
    }
    return $crew;
}

function demo_fleet(PDO $db, array $config, string $actor): array
{
    $models = ['Mercedes-Benz Sprinter', 'Renault Master', 'Fiat Ducato', 'Mercedes-Benz Sprinter', 'Renault Master', 'Fiat Ducato', 'Mercedes-Benz Sprinter'];
    $fleet = [];
    demo_actor($db, $actor);
    foreach ($models as $index => $model) {
        $status = 'available';
        if ($index === 5) {
            $status = 'maintenance';
        } elseif ($index === 6) {
            $status = 'out_of_service';
        }
        $fleet[] = ambulance_save($db, $config, $actor, [
            'plate' => 'SDA' . (8141 + $index),
            'model' => $model,
            'capacity' => 4,
            'operational_status' => $status
        ]);
    }
    return $fleet;
}

function demo_routes(PDO $db, array $config, string $actor, array $facilities, array $paths): array
{
    $names = [
        'Clínicas a Maciel', 'Maciel a Pasteur', 'Pasteur a Pereira Rossell',
        'Pereira Rossell a Español', 'Español a Saint Bois', 'Saint Bois a Clínicas'
    ];
    $routes = [];
    demo_actor($db, $actor);
    foreach ($facilities as $index => $origin) {
        $destination = $facilities[($index + 1) % count($facilities)];
        $routes[] = routes_save($db, $config, $actor, [
            'name' => $names[$index],
            'origin_name' => $origin['name'],
            'origin_address' => $origin['address'],
            'destination_name' => $destination['name'],
            'destination_address' => $destination['address'],
            'category' => 'interhospital',
            'direction' => 'one_way',
            'availability' => 'active',
            'notes' => 'Recorrido vial calculado por el router entre centros de Montevideo.',
            'distance_meters' => $paths[$index]['distance_meters'],
            'duration_seconds' => $paths[$index]['duration_seconds'],
            'stops' => []
        ]);
    }
    return $routes;
}

function demo_historical_dates(PDO $db, string $id, DateTimeImmutable $scheduled): void
{
    // Real saves reject dates older than yesterday. Create and complete the validated lifecycle
    // first, then backdate only demo chronology; application rules and status checks stay intact.
    $statement = $db->prepare('SELECT status,duration_seconds,scheduled_at,reserved_until FROM transfers WHERE id=?');
    $statement->execute([$id]);
    $transfer = $statement->fetch();
    $reservationLength = strtotime($transfer['reserved_until'] . ' UTC') - strtotime($transfer['scheduled_at'] . ' UTC');
    $reservedUntil = $scheduled->modify('+' . $reservationLength . ' seconds');
    $created = $scheduled->modify('-90 minutes');
    $started = $scheduled->modify('+2 minutes');
    $arrived = $started->modify('+' . $transfer['duration_seconds'] . ' seconds');
    $returning = $arrived->modify('+4 minutes');
    $finished = $returning->modify('+' . $transfer['duration_seconds'] . ' seconds');
    $startValue = null;
    $arrivalValue = null;
    if ($transfer['status'] === 'completed') {
        $startValue = $started->format('Y-m-d H:i:s');
        $arrivalValue = $arrived->format('Y-m-d H:i:s');
    } else {
        $finished = $scheduled->modify('-20 minutes');
    }
    $db->beginTransaction();
    try {
        $statement = $db->prepare('UPDATE transfers SET scheduled_at=?,reserved_until=?,started_at=?,arrived_at=?,finished_at=?,created_at=?,updated_at=? WHERE id=?');
        $statement->execute([
            $scheduled->format('Y-m-d H:i:s'), $reservedUntil->format('Y-m-d H:i:s'),
            $startValue, $arrivalValue, $finished->format('Y-m-d H:i:s'),
            $created->format('Y-m-d H:i:s'), $finished->format('Y-m-d H:i:s'), $id
        ]);
        $events = $db->prepare('UPDATE transfer_events SET created_at=? WHERE transfer_id=? AND to_status=?');
        $events->execute([$created->format('Y-m-d H:i:s'), $id, 'scheduled']);
        $events->execute([$started->format('Y-m-d H:i:s'), $id, 'in_progress']);
        $events->execute([$arrived->format('Y-m-d H:i:s'), $id, 'arrived']);
        $events->execute([$returning->format('Y-m-d H:i:s'), $id, 'returning']);
        $events->execute([$finished->format('Y-m-d H:i:s'), $id, $transfer['status']]);
        $statement = $db->prepare('UPDATE transfer_positions SET recorded_at=CASE WHEN progress=0 THEN ? ELSE ? END WHERE transfer_id=?');
        $statement->execute([$started->format('Y-m-d H:i:s'), $arrived->format('Y-m-d H:i:s'), $id]);
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function demo_transfers(PDO $db, array $config, string $actor, array $facilities, array $routes, array $fleet, array $crew, array $patients): array
{
    $days = [-29, -25, -21, -17, -13, -9, -5, -2, 0, 0, 1, 3];
    $transfers = [];
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Montevideo'));
    demo_actor($db, $actor);
    foreach ($days as $index => $day) {
        $routeIndex = $index % count($routes);
        $resourceIndex = $index % 4;
        $scheduled = $now;
        if ($day > 0) {
            $scheduled = $now->modify('+' . $day . ' days')->setTime(10 + $index % 3, 30);
        }
        $transfer = transfers_create($db, $config, $actor, [
            'route_template_id' => $routes[$routeIndex]['id'],
            'route_version' => $routes[$routeIndex]['version'],
            'ambulance_id' => $fleet[$resourceIndex]['id'],
            'driver_id' => $crew['drivers'][$resourceIndex]['id'],
            'companion_id' => $crew['copilots'][$resourceIndex]['id'],
            'patient_id' => $patients[$index]['id'],
            'origin_facility_id' => $facilities[$routeIndex]['id'],
            'destination_facility_id' => $facilities[($routeIndex + 1) % count($facilities)]['id'],
            'scheduled_at' => $scheduled->format('Y-m-d\TH:i:sP'),
            'cargo_type' => 'patient',
            'priority' => ['normal', 'high', 'low'][$index % 3],
            'reason' => ['consulta', 'estudio', 'alta'][$index % 3],
            'notes' => 'Coordinación interhospitalaria ficticia para la presentación académica.',
            'special_equipment' => $index % 3 === 0,
            'with_family' => $index % 2 === 0,
            'interdepartmental' => false,
            'critical_patient' => false,
            'create_token' => bin2hex(random_bytes(32))
        ]);
        if ($index < 6 || $index === 8 || $index === 9) {
            $transfer = transfers_change_status($db, $config, $actor, $transfer['id'], $transfer['version'], 'in_progress');
        }
        if ($index < 6 || $index === 9) {
            $transfer = transfers_change_status($db, $config, $actor, $transfer['id'], $transfer['version'], 'arrived');
            $transfer = transfers_change_status($db, $config, $actor, $transfer['id'], $transfer['version'], 'returning');
        }
        if ($index < 6) {
            $transfer = transfers_change_status($db, $config, $actor, $transfer['id'], $transfer['version'], 'completed');
        } elseif ($index === 6 || $index === 7) {
            $transfer = transfers_change_status($db, $config, $actor, $transfer['id'], $transfer['version'], 'cancelled');
        }
        if ($day < 0) {
            $historical = $now->modify($day . ' days')->setTime(9 + $index % 6, 15);
            demo_historical_dates($db, $transfer['id'], $historical->setTimezone(new DateTimeZone('UTC')));
        }
        $transfers[] = $transfer;
    }
    return $transfers;
}

function demo_pdf(string $title, array $lines): string
{
    // A small, standalone A4 PDF with standard Helvetica; no library or application dependency.
    $textLines = array_merge(['Hospital | Presentación académica', $title, ''], $lines, [
        '', 'Información completamente ficticia. Sin validez asistencial.', 'Montevideo, Uruguay'
    ]);
    $commands = "BT\n/F1 12 Tf\n50 790 Td\n22 TL\n";
    foreach ($textLines as $line) {
        $encoded = iconv('UTF-8', 'Windows-1252', $line);
        if ($encoded === false) {
            throw new RuntimeException('El informe contiene un carácter que no se puede escribir en el PDF.');
        }
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
        $commands .= '(' . $escaped . ") Tj\nT*\n";
    }
    $commands .= "ET\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen($commands) . ">>\nstream\n" . $commands . 'endstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $crossReference = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $crossReference . "\n%%EOF\n";
    return $pdf;
}

function demo_http(string $base, string $action, string $cookiePath, ?array $body = null, ?string $csrf = null): array
{
    $request = curl_init($base . '/api/v1/' . $action);
    $headers = ['Accept: application/json'];
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEFILE => $cookiePath,
        CURLOPT_COOKIEJAR => $cookiePath
    ]);
    if ($body !== null) {
        curl_setopt($request, CURLOPT_POST, true);
        if (isset($body['file'])) {
            curl_setopt($request, CURLOPT_POSTFIELDS, $body);
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
    }
    curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);
    $decoded = null;
    if (is_string($response)) {
        $decoded = json_decode($response, true);
    }
    if ($status < 200 || $status >= 300 || !is_array($decoded) || !($decoded['success'] ?? false)) {
        // Never dump request bodies, credentials, cookies or server logs.
        $message = $decoded['error']['message'] ?? 'El servidor local no respondió correctamente.';
        throw new RuntimeException('Carga validada por API (' . $status . '): ' . $message);
    }
    return $decoded['data'];
}

function demo_documents(PDO $db, array $config, array $accounts, array $patients, string $privateDirectory): void
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorText);
    if ($socket === false) {
        throw new RuntimeException('No se pudo reservar un puerto local para validar las cargas.');
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address . $config['base_path'];
    $environment = getenv();
    $environment['HOSPITAL_SECURE_COOKIES'] = 'false';
    $environment['HOSPITAL_APP_URL'] = $base . '/';
    $root = dirname(__DIR__, 3);
    $server = proc_open(
        [PHP_BINARY, '-d', 'opcache.enable_cli=1', '-S', $address, '-t', $root . '/public', $root . '/backend/php/dev-router.php'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $privateDirectory . '/upload-server.log', 'a'], 2 => ['file', $privateDirectory . '/upload-server.log', 'a']],
        $pipes,
        $root,
        $environment
    );
    if (!is_resource($server)) {
        throw new RuntimeException('No se pudo iniciar el servidor local de cargas.');
    }
    $temporaryFiles = [];
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @stream_socket_client('tcp://' . $address, $errorNumber, $errorText, 0.1);
            if ($connection !== false) {
                fclose($connection);
                $ready = true;
                break;
            }
            usleep(100000);
        }
        if (!$ready || !proc_get_status($server)['running']) {
            throw new RuntimeException('El servidor local de cargas no pudo iniciarse.');
        }
        $titles = [
            'Informe de evolución clínica', 'Resumen de laboratorio', 'Constancia de traslado',
            'Informe de seguimiento ambulatorio', 'Control de laboratorio', 'Coordinación interhospitalaria'
        ];
        $categories = $db->query('SELECT id,code FROM document_categories')->fetchAll(PDO::FETCH_KEY_PAIR);
        $categoryIds = array_flip($categories);
        $categoryCodes = ['medical_report', 'laboratory_study', 'administrative'];
        foreach ($accounts as $role => $account) {
            $cookiePath = $privateDirectory . '/' . $role . '.cookies';
            file_put_contents($cookiePath, '');
            chmod($cookiePath, 0600);
            $temporaryFiles[] = $cookiePath;
            $csrf = demo_http($base, 'auth.php?action=csrf', $cookiePath)['csrf_token'];
            demo_http($base, 'auth.php?action=login', $cookiePath, [
                'username' => $account['username'],
                'password' => $account['password']
            ], $csrf);
            $csrf = demo_http($base, 'auth.php?action=csrf', $cookiePath)['csrf_token'];
            $first = 0;
            if ($role === 'records_manager') {
                $first = 3;
            }
            for ($index = $first; $index < $first + 3; $index++) {
                $pdfPath = $privateDirectory . '/informe-' . ($index + 1) . '.pdf';
                $date = (new DateTimeImmutable('today', new DateTimeZone('America/Montevideo')))->modify('-' . (2 + $index * 3) . ' days')->format('Y-m-d');
                $pdf = demo_pdf($titles[$index], [
                    'Fecha: ' . $date,
                    'Referencia ficticia: ' . (4201 + $index),
                    'Área: coordinación clínica y traslados',
                    '',
                    'Resumen: control programado y seguimiento de la atención.',
                    'Estado: documentación completa para coordinación interna.',
                    'Observaciones: se presenta un caso simulado, sin datos reales.'
                ]);
                file_put_contents($pdfPath, $pdf);
                chmod($pdfPath, 0600);
                $temporaryFiles[] = $pdfPath;
                $token = demo_http($base, 'documents.php?action=create-token', $cookiePath)['create_token'];
                demo_http($base, 'documents.php?action=create', $cookiePath, [
                    'title' => $titles[$index],
                    'description' => 'Informe ficticio de presentación. Sin información de personas reales ni fotografías.',
                    'category_id' => (string)$categoryIds[$categoryCodes[$index % 3]],
                    'patient_id' => (string)$patients[$index]['id'],
                    'document_date' => $date,
                    'create_token' => $token,
                    'file' => new CURLFile($pdfPath, 'application/pdf', 'informe-' . ($index + 1) . '.pdf')
                ], $csrf);
            }
            demo_http($base, 'auth.php?action=logout', $cookiePath, [], $csrf);
        }
    } finally {
        proc_terminate($server);
        proc_close($server);
        foreach ($temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

function demo_survey_input(string $title, string $category): array
{
    return [
        'title' => $title,
        'description' => 'Evaluación ficticia para visualizar resultados de la presentación.',
        'category' => $category,
        'area' => 'Atención y coordinación',
        'notes' => 'Respuestas sintéticas; no representan opiniones de personas reales.',
        'form_title' => $title,
        'form_description' => 'Contanos cómo fue la atención recibida.',
        'color' => 'blue',
        'config' => [
            'audience' => 'everyone',
            'access' => 'shared_link',
            'identity' => 'anonymous',
            'limit_enabled' => false,
            'opens_enabled' => false,
            'closes_enabled' => false,
            'completion_message' => 'Gracias por ayudarnos a mejorar la atención.'
        ],
        'items' => [
            ['id' => 'satisfaccion', 'type' => 'rating', 'title' => '¿Cómo calificás la atención recibida?', 'required' => true, 'rating_max' => 5],
            ['id' => 'puntualidad', 'type' => 'single_choice', 'title' => '¿Cómo fue el tiempo de espera?', 'required' => true, 'options' => ['Menos de 15 minutos', 'Entre 15 y 30 minutos', 'Entre 30 y 60 minutos', 'Más de una hora']],
            ['id' => 'aspectos', 'type' => 'multiple_choice', 'title' => '¿Qué aspectos destacarías?', 'required' => true, 'options' => ['Trato del equipo', 'Claridad de la información', 'Comodidad', 'Coordinación']],
            ['id' => 'recomendacion', 'type' => 'linear_scale', 'title' => '¿Recomendarías este servicio?', 'required' => true, 'scale_min' => 1, 'scale_max' => 10, 'scale_start' => 'Poco probable', 'scale_end' => 'Muy probable'],
            ['id' => 'comentario', 'type' => 'long_text', 'title' => '¿Qué podríamos mejorar?', 'required' => false]
        ]
    ];
}

function demo_surveys(PDO $db, array $config, array $accounts, string $admin): void
{
    $definitions = [
        ['owner' => $accounts['dispatcher']['id'], 'title' => 'Experiencia en traslados interhospitalarios', 'category' => 'transfers', 'responses' => 32],
        ['owner' => $accounts['records_manager']['id'], 'title' => 'Calidad de la atención y la información', 'category' => 'patient_care', 'responses' => 36],
        ['owner' => $admin, 'title' => 'Comunicación entre equipos de salud', 'category' => 'staff', 'responses' => 0]
    ];
    $comments = [
        'La coordinación fue clara y el equipo muy amable.',
        'Sería útil recibir el horario con mayor anticipación.',
        'Buena atención durante todo el recorrido.',
        'La información de ingreso fue fácil de comprender.',
        'Podría mejorar la señalización de la sala de espera.',
        'El personal respondió todas las consultas.'
    ];
    foreach ($definitions as $definition) {
        $actor = (string)$definition['owner'];
        demo_actor($db, $actor);
        $survey = surveys_save($db, $actor, demo_survey_input($definition['title'], $definition['category']));
        if ($definition['responses'] === 0) {
            continue;
        }
        $survey = surveys_transition($db, $config, $actor, $survey['id'], $survey['version'], 'publish');
        $links = surveys_links($db, $config, $actor, $survey['id']);
        $token = $links[0]['token'];
        for ($index = 0; $index < $definition['responses']; $index++) {
            $public = surveys_public_get($db, $config, $token);
            $rating = [5, 4, 5, 3, 4, 5, 4, 2][$index % 8];
            surveys_submit($db, $config, $token, [
                'version' => $public['version'],
                'submit_token' => $public['submit_token'],
                'participant_name' => '',
                'answers' => [
                    ['question_id' => 'satisfaccion', 'value' => $rating],
                    ['question_id' => 'puntualidad', 'value' => $index % 4],
                    ['question_id' => 'aspectos', 'value' => [$index % 4, ($index + 1) % 4]],
                    ['question_id' => 'recomendacion', 'value' => 6 + $index % 5],
                    ['question_id' => 'comentario', 'value' => $comments[$index % count($comments)]]
                ]
            ]);
        }
    }
}

function demo_verify(PDO $db, array $admin): array
{
    $expected = [
        'users' => 3, 'patients' => 15, 'doctor_profiles' => 8, 'personnel_profiles' => 10,
        'ambulances' => 7, 'facilities' => 6, 'route_templates' => 6, 'transfers' => 12,
        'documents' => 6, 'document_versions' => 6, 'stored_files' => 6,
        'surveys' => 3, 'survey_responses' => 68
    ];
    foreach ($expected as $table => $count) {
        if ((int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== $count) {
            throw new RuntimeException('Cantidad inesperada de registros en ' . $table . '.');
        }
    }
    $minimum = PHP_INT_MAX;
    foreach ($db->query('SELECT geometry FROM route_templates UNION ALL SELECT geometry FROM transfers')->fetchAll() as $route) {
        $points = count(json_decode($route['geometry'], true, 64, JSON_THROW_ON_ERROR));
        if ($points <= 20) {
            throw new RuntimeException('Una ruta o traslado tiene 20 puntos o menos.');
        }
        $minimum = min($minimum, $points);
    }
    $current = users_by_id($db, (string)$admin['id']);
    if ($current['username'] !== $admin['username'] || !hash_equals($current['password_hash'], $admin['password_hash'])) {
        throw new RuntimeException('La cuenta administradora no se conservó.');
    }
    return ['counts' => $expected, 'minimum_path_points' => $minimum];
}

function demo_run(array $arguments): void
{
    if (in_array('--help', $arguments, true)) {
        echo "Uso: php backend/php/cli/demo-data.php [--reset] [--yes]\n";
        echo "--reset reemplaza los registros y archivos; conserva la primera cuenta administradora activa.\n";
        echo "--yes evita la confirmación. Solo bases locales/dev, con router HTTPS disponible.\n";
        return;
    }
    if (array_diff($arguments, ['--reset', '--yes'])) {
        throw new RuntimeException('Opción desconocida. Usá --help.');
    }
    $config = Hospital\Config::load();
    demo_require_local_database($config);
    $db = Hospital\Database::connect($config);
    $admin = demo_main_admin($db);
    $reset = in_array('--reset', $arguments, true);
    if ($reset && !in_array('--yes', $arguments, true)) {
        echo 'Se borrarán registros y archivos de ' . $config['db']['name'] . '; se conservará ' . $admin['username'] . ".\n";
        fwrite(STDOUT, '¿Continuar? Escribí SI: ');
        $answer = fgets(STDIN);
        if ($answer === false || trim($answer) !== 'SI') {
            echo "Cancelado. No se modificaron los datos.\n";
            return;
        }
    }
    if (!$reset) {
        foreach (['patients', 'staff_roles', 'ambulances', 'facilities', 'documents', 'surveys'] as $table) {
            if ((int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
                throw new RuntimeException('La base contiene registros. Usá --reset para reemplazarlos explícitamente.');
            }
        }
        if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 1) {
            throw new RuntimeException('Hay otras cuentas. Usá --reset para reemplazarlas explícitamente.');
        }
    }
    $GLOBALS['request_id'] = 'demo-' . bin2hex(random_bytes(12));
    $GLOBALS['page_locale'] = 'es-UY';
    $workerLock = transfer_lock_name($config, 'worker');
    $statement = $db->prepare('SELECT GET_LOCK(?,0)');
    $statement->execute([$workerLock]);
    if ((int)$statement->fetchColumn() !== 1) {
        throw new RuntimeException('Detené el worker de simulación antes de reemplazar los datos.');
    }
    try {
        echo "Comprobando las seis rutas con el router, antes de borrar datos...\n";
        $definitions = demo_facilities();
        $paths = demo_router_preflight($db, $config, $definitions);
        $privateDirectory = demo_private_directory($config);
        if ($reset) {
            demo_reset($db, $config, $admin);
        }
        // These cache entries are fresh responses from the live preflight, never invented paths.
        $statement = $db->prepare('INSERT INTO route_map_cache (cache_key,payload,expires_at) VALUES (?,?,?)');
        foreach ($paths as $path) {
            $statement->execute([$path['cache_key'], $path['payload'], $path['expires_at']]);
        }
        $accounts = demo_accounts($db, $config, $admin, $privateDirectory);
        $dispatcher = $accounts['dispatcher']['id'];
        $recordsManager = $accounts['records_manager']['id'];
        $facilities = demo_save_facilities($db, $config, $dispatcher, $definitions);
        $doctors = demo_doctors($db, $config, $recordsManager);
        $patients = demo_patients($db, $config, $recordsManager, $doctors);
        $crew = demo_personnel($db, $config, $dispatcher, $recordsManager);
        $fleet = demo_fleet($db, $config, $dispatcher);
        echo "Cuentas, personas, destinos y flota creados.\n";
        $routes = demo_routes($db, $config, $dispatcher, $facilities, $paths);
        demo_transfers($db, $config, $dispatcher, $facilities, $routes, $fleet, $crew, $patients);
        echo "Seis rutas y doce traslados validados; preparando PDF y encuestas...\n";
        demo_documents($db, $config, $accounts, $patients, $privateDirectory);
        demo_surveys($db, $config, $accounts, (string)$admin['id']);
        $result = demo_verify($db, $admin);
        file_put_contents($privateDirectory . '/verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        chmod($privateDirectory . '/verification.json', 0600);
        echo "Demostración creada: 15 pacientes, 8 médicos, 10 integrantes de tripulación, 7 ambulancias, 6 destinos y rutas, 12 traslados, 6 PDF y 3 encuestas (68 respuestas).\n";
        echo 'Todas las rutas y traslados tienen al menos ' . $result['minimum_path_points'] . " puntos.\n";
        echo 'Contraseñas privadas (no se imprimen): ' . $privateDirectory . "/credentials.json\n";
        echo "Para mover las ambulancias: simulate-transfers.php start y simulate-transfers.php worker.\n";
    } finally {
        $statement = $db->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([$workerLock]);
    }
}

// Importing helpers for focused checks cannot seed data accidentally.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        demo_run(array_slice($argv, 1));
    } catch (Throwable $error) {
        $message = $error->getMessage();
        if ($error instanceof PDOException) {
            $message = 'Revisá la base local y su esquema. La operación puede haberse interrumpido; volvé a ejecutar --reset.';
        }
        fwrite(STDERR, 'No se pudo preparar la demostración: ' . $message . "\n");
        exit(1);
    }
}
