<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

// Availability management is professional-only.
if (!function_exists('is_professional') || !is_professional()) {
    redirect('index.php');
}

$user_id = get_user_id();
$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';

$pro_stmt = $conn->prepare("SELECT is_accepting_patients, consultation_fee, availability_schedule FROM professionals WHERE user_id = ? LIMIT 1");
$pro_stmt->bind_param('i', $user_id);
$pro_stmt->execute();
$professional_settings = $pro_stmt->get_result()->fetch_assoc();
$pro_stmt->close();

if (!$professional_settings) {
    // Should not happen if professional signup created a row, but handle gracefully.
    redirect('index.php');
}

function parse_availability_events($availability_schedule_raw) {
    $availability_schedule_raw = trim((string)$availability_schedule_raw);
    if ($availability_schedule_raw === '') {
        return [];
    }

    $decoded = json_decode($availability_schedule_raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    // New format: { timezone: '...', events: [ { start, end, title? } ] }
    if (isset($decoded['events']) && is_array($decoded['events'])) {
        return $decoded['events'];
    }

    // Back-compat: stored directly as an array of events
    if (array_is_list($decoded)) {
        return $decoded;
    }

    return [];
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availability'])) {
    $is_accepting_patients = (int)($_POST['is_accepting_patients'] ?? 0);

    $consultation_fee_raw = trim((string)($_POST['consultation_fee'] ?? ''));
    $consultation_fee = 0.0;
    if ($consultation_fee_raw !== '') {
        if (!is_numeric($consultation_fee_raw)) {
            $error = 'Consultation fee must be a valid number.';
        } else {
            $consultation_fee = (float)$consultation_fee_raw;
            if ($consultation_fee < 0) {
                $error = 'Consultation fee cannot be negative.';
            }
        }
    }

    $events_raw = trim((string)($_POST['availability_events'] ?? '[]'));
    $events_decoded = json_decode($events_raw, true);
    if ($error === '' && (!is_array($events_decoded) || json_last_error() !== JSON_ERROR_NONE)) {
        $error = 'Calendar availability data is invalid. Please try again.';
        $events_decoded = [];
    }

    // Validate events structure
    $validated_events = [];
    if ($error === '') {
        foreach ($events_decoded as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $start = (string)($ev['start'] ?? '');
            $end = (string)($ev['end'] ?? '');
            if ($start === '' || $end === '') {
                continue;
            }
            // FullCalendar posts ISO strings; keep as-is.
            $validated_events[] = [
                'title' => 'Available',
                'start' => $start,
                'end' => $end,
            ];
        }
    }

    if ($error === '') {
        $availability_store = json_encode([
            'timezone' => 'local',
            'events' => $validated_events,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $upd = $conn->prepare("UPDATE professionals SET is_accepting_patients = ?, consultation_fee = ?, availability_schedule = ? WHERE user_id = ?");
        if ($upd) {
            $upd->bind_param('idsi', $is_accepting_patients, $consultation_fee, $availability_store, $user_id);
            if ($upd->execute()) {
                $message = 'Availability saved successfully!';
                $professional_settings['is_accepting_patients'] = $is_accepting_patients;
                $professional_settings['consultation_fee'] = $consultation_fee;
                $professional_settings['availability_schedule'] = $availability_store;
            } else {
                $error = 'Could not save availability. Please try again.';
            }
            $upd->close();
        } else {
            $error = 'Could not save availability. Please try again.';
        }
    }
}

$existing_events = parse_availability_events($professional_settings['availability_schedule'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

    <style>
        .settings-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .settings-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .settings-card {
            background: var(--bg-card, #F8F9F7);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .setting-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .setting-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .setting-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .setting-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            font-family: inherit;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        #availability-calendar {
            margin-top: 1rem;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            background: white;
            padding: 12px;
        }

        .hint {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(111, 207, 151, 0.15);
            color: #2d7a4d;
            border-left-color: var(--success);
        }

        .alert-error {
            background: rgba(235, 87, 87, 0.15);
            color: #c72e2e;
            border-left-color: var(--error);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Availability</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
                    </a>
                </div>
            </div>

            <div class="content-area">
                <div class="settings-container">
                    <div class="settings-header">
                        <h1 style="margin:0 0 8px 0;">Availability & Intake</h1>
                        <p style="margin:0; opacity:0.9;">Set availability with a calendar and control new client requests.</p>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="settings-card">
                        <div class="card-title">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z"/></svg>
                            Availability & Intake
                        </div>

                        <form method="POST" action="">
                            <div class="setting-group">
                                <div class="setting-label">Accepting New Clients</div>
                                <div class="setting-description">Controls whether patients can request sessions with you from the Professionals page.</div>
                                <div class="form-group">
                                    <label for="is_accepting_patients" style="display:none;">Accepting New Clients</label>
                                    <select id="is_accepting_patients" name="is_accepting_patients">
                                        <option value="1" <?php echo !empty($professional_settings['is_accepting_patients']) ? 'selected' : ''; ?>>Yes — Accepting requests</option>
                                        <option value="0" <?php echo empty($professional_settings['is_accepting_patients']) ? 'selected' : ''; ?>>No — Pause requests</option>
                                    </select>
                                </div>
                            </div>

                            <div class="setting-group">
                                <div class="setting-label">Consultation Fee (per session)</div>
                                <div class="setting-description">This is shown to patients on the Professionals page.</div>
                                <div class="form-group">
                                    <label for="consultation_fee" style="display:none;">Consultation Fee</label>
                                    <input id="consultation_fee" name="consultation_fee" type="number" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($professional_settings['consultation_fee'] ?? '0')); ?>">
                                </div>
                            </div>

                            <div class="setting-group">
                                <div class="setting-label">Availability Calendar</div>
                                <div class="setting-description">Drag/select time ranges to mark when you are available. Click an available block to remove it.</div>

                                <input type="hidden" id="availability_events" name="availability_events" value="<?php echo htmlspecialchars(json_encode($existing_events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>">
                                <div id="availability-calendar"></div>
                                <div class="hint">Tip: Select time ranges. Click an event to delete it.</div>
                            </div>

                            <div class="button-group">
                                <button type="submit" name="save_availability" class="btn btn-primary">Save Availability</button>
                                <a href="professionals.php" class="btn btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center;">Open Session Workspace</a>
                                <button type="button" class="btn btn-secondary" id="clear-availability">Clear Calendar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const initialEvents = (() => {
            try {
                const raw = document.getElementById('availability_events').value || '[]';
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch { return []; }
        })();

        const calendarEl = document.getElementById('availability-calendar');
        const hiddenField = document.getElementById('availability_events');

        function syncHidden(calendar) {
            const events = calendar.getEvents().map(ev => ({
                start: ev.start ? ev.start.toISOString() : null,
                end: ev.end ? ev.end.toISOString() : null,
                title: 'Available'
            })).filter(e => e.start && e.end);
            hiddenField.value = JSON.stringify(events);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                nowIndicator: true,
                selectable: true,
                selectMirror: true,
                editable: true,
                allDaySlot: false,
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay'
                },
                events: initialEvents.map(e => ({
                    title: 'Available',
                    start: e.start,
                    end: e.end,
                    backgroundColor: 'rgba(127, 175, 163, 0.35)',
                    borderColor: 'rgba(127, 175, 163, 0.9)'
                })),
                select: function(info) {
                    calendar.addEvent({
                        title: 'Available',
                        start: info.start,
                        end: info.end,
                        backgroundColor: 'rgba(127, 175, 163, 0.35)',
                        borderColor: 'rgba(127, 175, 163, 0.9)'
                    });
                    syncHidden(calendar);
                    calendar.unselect();
                },
                eventClick: function(info) {
                    if (confirm('Remove this availability block?')) {
                        info.event.remove();
                        syncHidden(calendar);
                    }
                },
                eventChange: function() {
                    syncHidden(calendar);
                },
                eventAdd: function() {
                    syncHidden(calendar);
                },
                eventRemove: function() {
                    syncHidden(calendar);
                }
            });

            calendar.render();
            syncHidden(calendar);

            document.getElementById('clear-availability').addEventListener('click', function() {
                if (!confirm('Clear all availability blocks?')) return;
                calendar.getEvents().forEach(e => e.remove());
                syncHidden(calendar);
            });
        });
    </script>
</body>
</html>
