<?php
include '../db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') { exit('Unauthorized'); }
$student_id = (int)$_SESSION['user_id'];

if (isset($_POST['action']) && $_POST['action'] === 'count_notifications') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 AND user_type = 'student'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    echo (int)$count;
    exit;
}

if (!isset($_POST['section'])) { exit; }
$section = $_POST['section'];

function badge($text, $color = 'gray') {
    $map = [
        'green' => 'bg-green-100 text-green-700 ring-green-200',
        'red'   => 'bg-red-100 text-red-700 ring-red-200',
        'yellow'=> 'bg-yellow-100 text-yellow-700 ring-yellow-200',
        'blue'  => 'bg-blue-100 text-blue-700 ring-blue-200',
        'gray'  => 'bg-gray-100 text-gray-700 ring-gray-200',
        'purple'=> 'bg-purple-100 text-purple-700 ring-purple-200',
    ];
    $cls = $map[$color] ?? $map['gray'];
    return '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full ring-1 '.$cls.'">'.htmlspecialchars($text).'</span>';
}

if ($section === 'overview') {
    // Enrolled units count
    $stmt_units = $conn->prepare("SELECT COUNT(*) FROM student_enrollments WHERE student_id = ?");
    $stmt_units->bind_param("i", $student_id);
    $stmt_units->execute(); $stmt_units->bind_result($enrolled_units); $stmt_units->fetch(); $stmt_units->close();

    // Attendance rate
    $stmt_attendance = $conn->prepare("
        SELECT SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*) * 100 AS attendance_rate
        FROM attendance_records WHERE student_id = ?
    ");
    $stmt_attendance->bind_param("i", $student_id);
    $stmt_attendance->execute(); $stmt_attendance->bind_result($attendance_rate); $stmt_attendance->fetch(); $stmt_attendance->close();
    $attendance_rate = round($attendance_rate ?? 0, 2);
    $attWidth = max(0, min(100, (float)$attendance_rate));

    // Upcoming sessions
    $stmt_upcoming = $conn->prepare("
        SELECT cs.session_date, cs.start_time, cu.unit_name, cs.venue
        FROM class_sessions cs
        JOIN course_units cu ON cs.unit_id = cu.unit_id
        JOIN student_enrollments se ON cu.unit_id = se.unit_id
        WHERE se.student_id = ? AND cs.session_date >= CURDATE()
        ORDER BY cs.session_date ASC, cs.start_time ASC
        LIMIT 3
    ");
    $stmt_upcoming->bind_param("i", $student_id);
    $stmt_upcoming->execute();
    $upcoming_result = $stmt_upcoming->get_result();

    // Recent attendance
    $stmt_recent_att = $conn->prepare("
        SELECT ar.status, cs.session_date, cu.unit_name
        FROM attendance_records ar
        JOIN class_sessions cs ON ar.session_id = cs.session_id
        JOIN course_units cu ON cs.unit_id = cu.unit_id
        WHERE ar.student_id = ?
        ORDER BY cs.session_date DESC
        LIMIT 5
    ");
    $stmt_recent_att->bind_param("i", $student_id);
    $stmt_recent_att->execute();
    $recent_att_result = $stmt_recent_att->get_result();

    // Recent notifications
    $stmt_recent_notif = $conn->prepare("
        SELECT n.message, n.created_at, cu.unit_name
        FROM notifications n
        LEFT JOIN course_units cu ON n.unit_id = cu.unit_id
        WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'student'
        ORDER BY n.created_at DESC
        LIMIT 3
    ");
    $stmt_recent_notif->bind_param("i", $student_id);
    $stmt_recent_notif->execute();
    $recent_notif_result = $stmt_recent_notif->get_result();

    ?>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Dashboard Overview</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Quick Stats -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Quick Stats</h3>
            <?= badge('Live', 'green'); ?>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-blue-50 p-4">
              <div class="text-sm text-gray-600">Enrolled Units</div>
              <div class="mt-1 text-2xl font-bold text-blue-700"><?= (int)$enrolled_units; ?></div>
            </div>
            <div class="rounded-xl bg-emerald-50 p-4">
              <div class="text-sm text-gray-600">Attendance</div>
              <div class="mt-1 text-2xl font-bold text-emerald-700"><?= htmlspecialchars($attendance_rate); ?>%</div>
            </div>
          </div>
          <div class="mt-5">
            <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
              <span>Overall Attendance</span><span><?= htmlspecialchars($attendance_rate); ?>%</span>
            </div>
            <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-3 rounded-full bg-gradient-to-r from-blue-500 to-emerald-500" style="width: <?= $attWidth; ?>%;"></div>
            </div>
          </div>
        </div>

        <!-- Upcoming Sessions -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Upcoming Sessions</h3>
            <span class="text-sm text-gray-500"><?= $upcoming_result->num_rows; ?> events</span>
          </div>
          <div class="mt-4 space-y-3">
            <?php if ($upcoming_result->num_rows > 0): ?>
              <?php while ($row = $upcoming_result->fetch_assoc()): ?>
                <div class="flex items-start gap-3 rounded-xl border border-gray-100 p-3 hover:bg-gray-50">
                  <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-semibold">
                      <!-- calendar icon -->
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4M16 2v4M3 10h18M5 6h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/>
                      </svg>
                    </div>
                  </div>
                  <div class="min-w-0">
                    <div class="font-medium text-gray-900 truncate"><?= htmlspecialchars($row['unit_name']); ?></div>
                    <div class="text-sm text-gray-600">
                      <?= htmlspecialchars($row['session_date']); ?> • <?= htmlspecialchars($row['start_time']); ?> • <?= htmlspecialchars($row['venue']); ?>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500">No upcoming sessions.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Attendance -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Recent Attendance</h3>
          </div>
          <div class="mt-4 space-y-2">
            <?php if ($recent_att_result->num_rows > 0): ?>
              <?php while ($row = $recent_att_result->fetch_assoc()):
                $status = $row['status'];
                $color = ($status === 'Present') ? 'green' : (($status === 'Late') ? 'yellow' : 'red');
              ?>
                <div class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2">
                  <div class="min-w-0">
                    <div class="font-medium text-gray-900 truncate"><?= htmlspecialchars($row['unit_name']); ?></div>
                    <div class="text-sm text-gray-600"><?= htmlspecialchars($row['session_date']); ?></div>
                  </div>
                  <div><?= badge($status, $color); ?></div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500">No recent records.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Recent Notifications -->
      <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">Recent Notifications</h3>
        </div>
        <div class="mt-4 space-y-2">
          <?php if ($recent_notif_result->num_rows > 0): ?>
            <?php while ($row = $recent_notif_result->fetch_assoc()): ?>
              <div class="rounded-xl bg-purple-50 border border-purple-200 p-3">
                <div class="flex items-center justify-between">
                  <div class="text-sm text-gray-700"><?= htmlspecialchars($row['message']); ?></div>
                  <div class="flex items-center gap-2">
                    <?= badge('Student', 'purple'); ?>
                    <span class="text-xs text-gray-500"><?= htmlspecialchars($row['created_at']); ?></span>
                  </div>
                </div>
                <?php if (!empty($row['unit_name'])): ?>
                  <div class="mt-1 text-xs text-gray-600">Unit: <?= htmlspecialchars($row['unit_name']); ?></div>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500">No notifications yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    exit;
}

if ($section === 'enrolled_units') {
    $stmt = $conn->prepare("
        SELECT cu.unit_id, cu.unit_name, cu.unit_code
        FROM student_enrollments se
        JOIN course_units cu ON se.unit_id = cu.unit_id
        WHERE se.student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Enrolled Course Units</h2>
        <div class="text-sm text-gray-500"><?= $result->num_rows; ?> units</div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="rounded-2xl border border-gray-200 bg-white p-5 hover:shadow-sm transition">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-sm text-gray-500"><?= htmlspecialchars($row['unit_code']); ?></div>
                <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($row['unit_name']); ?></div>
              </div>
            </div>
            <div class="mt-4">
              <button onclick="loadSection('unit_sessions?unit_id=<?= (int)$row['unit_id']; ?>')"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-3 py-2 hover:bg-blue-500">
                View Sessions
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
    <?php
    exit;
}

if (strpos($section, 'unit_sessions') === 0) {
    parse_str(parse_url($section, PHP_URL_QUERY), $params);
    if (!isset($params['unit_id']) || !is_numeric($params['unit_id'])) {
        echo '<div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700">Invalid unit.</div>';
        exit;
    }
    $unit_id = (int)$params['unit_id'];

    $stmt = $conn->prepare("SELECT unit_name FROM course_units WHERE unit_id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $unitRow = $stmt->get_result()->fetch_assoc();
    $unitName = htmlspecialchars($unitRow['unit_name'] ?? ('Unit #'.$unit_id));

    $stmt = $conn->prepare("
        SELECT session_id, session_date, start_time, end_time, venue, session_topic
        FROM class_sessions
        WHERE unit_id = ?
        ORDER BY session_date DESC
    ");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Sessions • <?= $unitName; ?></h2>
        <div class="text-sm text-gray-500"><?= $result->num_rows; ?> sessions</div>
      </div>
      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-700">
            <tr>
              <th class="px-4 py-3 text-left">Date</th>
              <th class="px-4 py-3 text-left">Time</th>
              <th class="px-4 py-3 text-left">Venue</th>
              <th class="px-4 py-3 text-left">Topic</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3"><?= htmlspecialchars($row['session_date']); ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($row['start_time'].' - '.$row['end_time']); ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($row['venue']); ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($row['session_topic']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <button onclick="loadSection('enrolled_units')" class="inline-flex items-center gap-2 text-blue-700 hover:underline">
        ← Back to Units
      </button>
    </div>
    <?php
    exit;
}

if ($section === 'attendance') {
    $stmt = $conn->prepare("
        SELECT cu.unit_name, ar.status, cs.session_date, cs.session_id
        FROM attendance_records ar
        JOIN class_sessions cs ON ar.session_id = cs.session_id
        JOIN course_units cu ON cs.unit_id = cu.unit_id
        WHERE ar.student_id = ?
        ORDER BY cu.unit_name ASC, cs.session_date DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $grouped = [];
    while ($row = $result->fetch_assoc()) {
        $grouped[$row['unit_name']][] = $row;
    }
    ?>
    <div class="space-y-4">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Attendance Records</h2>
          <p class="text-gray-600 text-sm">Filter by unit or status. (Client-side filter)</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <input id="attSearch" type="text" placeholder="Search unit…" class="w-56 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <select id="attStatus" class="rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All statuses</option>
            <option value="Present">Present</option>
            <option value="Absent">Absent</option>
            <option value="Late">Late</option>
          </select>
        </div>
      </div>

      <?php if (empty($grouped)): ?>
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500">No attendance records found.</div>
      <?php else: ?>
        <div id="attContainer" class="space-y-6">
          <?php foreach ($grouped as $unit => $sessions): ?>
            <section class="att-block" data-unit="<?= htmlspecialchars($unit); ?>">
              <h3 class="text-lg font-semibold mt-2"><?= htmlspecialchars($unit); ?></h3>
              <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white mt-2">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left">Date</th>
                      <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <?php foreach ($sessions as $s):
                      $status = $s['status'];
                      $rowCls = ($status === 'Present') ? 'bg-green-50' : (($status === 'Late') ? 'bg-yellow-50' : 'bg-red-50');
                      $badgeColor = ($status === 'Present') ? 'green' : (($status === 'Late') ? 'yellow' : 'red');
                    ?>
                      <tr class="hover:bg-gray-50 att-row <?= $rowCls; ?>" data-status="<?= htmlspecialchars($status); ?>">
                        <td class="px-4 py-3"><?= htmlspecialchars($s['session_date']); ?></td>
                        <td class="px-4 py-3"><?= badge($status, $badgeColor); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
        <script>
          (function(){
            const q = document.getElementById('attSearch');
            const st = document.getElementById('attStatus');
            const blocks = [...document.querySelectorAll('#attContainer .att-block')];
            function apply(){
              const term = (q.value || '').toLowerCase();
              const status = st.value;
              blocks.forEach(b => {
                const unit = (b.getAttribute('data-unit') || '').toLowerCase();
                const unitMatch = unit.includes(term);
                let anyVisible = false;
                b.querySelectorAll('.att-row').forEach(r => {
                  const stMatch = !status || r.getAttribute('data-status') === status;
                  const show = unitMatch && stMatch;
                  r.style.display = show ? '' : 'none';
                  if (show) anyVisible = true;
                });
                b.style.display = anyVisible ? '' : 'none';
              });
            }
            q.addEventListener('input', apply);
            st.addEventListener('change', apply);
          })();
        </script>
      <?php endif; ?>
    </div>
    <?php
    exit;
}

if ($section === 'statistics') {
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late,
            COUNT(*) AS total
        FROM attendance_records WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $present = (int)($stats['present'] ?? 0);
    $absent  = (int)($stats['absent'] ?? 0);
    $late    = (int)($stats['late'] ?? 0);
    $total   = (int)($stats['total'] ?? 0);
    $present_rate = $total > 0 ? round(($present / $total) * 100, 2) : 0;

    $pW = $total ? ($present/$total*100) : 0;
    $lW = $total ? ($late/$total*100) : 0;
    $aW = $total ? ($absent/$total*100) : 0;
    ?>
    <div class="space-y-5">
      <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Attendance Statistics</h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Total Sessions</div>
          <div class="mt-1 text-3xl font-bold"><?= $total; ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Present</div>
          <div class="mt-1 text-3xl font-bold text-emerald-700"><?= $present; ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">On-time Rate</div>
          <div class="mt-1 text-3xl font-bold text-blue-700"><?= htmlspecialchars($present_rate); ?>%</div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-5">
        <div class="text-sm text-gray-600 mb-2">Distribution</div>
        <div class="w-full h-4 bg-gray-100 rounded-full overflow-hidden flex">
          <div class="h-4 bg-emerald-500" style="width: <?= $pW; ?>%"></div>
          <div class="h-4 bg-yellow-400" style="width: <?= $lW; ?>%"></div>
          <div class="h-4 bg-red-500" style="width: <?= $aW; ?>%"></div>
        </div>
        <div class="mt-3 flex flex-wrap gap-3 text-sm">
          <?= badge("Present: $present", 'green'); ?>
          <?= badge("Late: $late", 'yellow'); ?>
          <?= badge("Absent: $absent", 'red'); ?>
        </div>
      </div>
    </div>
    <?php
    exit;
}

if ($section === 'notifications') {
    $stmt = $conn->prepare("
        SELECT n.notification_id, n.message, n.created_at, cu.unit_name
        FROM notifications n
        LEFT JOIN course_units cu ON n.unit_id = cu.unit_id
        WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'student'
        ORDER BY n.created_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // mark as read
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 AND user_type = 'student'");
    $update->bind_param("i", $student_id);
    $update->execute();
    ?>
    <div class="space-y-4">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Notifications</h2>
          <p class="text-gray-600 text-sm">Search by message or unit. (Client-side filter)</p>
        </div>
        <div class="flex gap-2">
          <input id="notifSearch" type="text" placeholder="Search notifications…" class="w-72 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div id="notifContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $msg = htmlspecialchars($row['message']);
            $unit = htmlspecialchars($row['unit_name'] ?? '');
            $created = htmlspecialchars($row['created_at']);
          ?>
          <div class="notif-card rounded-2xl border border-gray-200 bg-white p-4" data-text="<?= strtolower($msg . ' ' . $unit); ?>">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm text-gray-800 break-words"><?= $msg; ?></div>
                <div class="mt-1 text-xs text-gray-600">
                  <span><?= $created; ?></span>
                  <?php if ($unit): ?>
                    <span class="mx-2">•</span><span>Unit: <?= $unit; ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <?= badge('Info', 'blue'); ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

      <script>
        (function(){
          const q = document.getElementById('notifSearch');
          const cards = [...document.querySelectorAll('#notifContainer .notif-card')];
          function apply(){
            const term = (q.value || '').toLowerCase();
            cards.forEach(c => {
              const t = c.getAttribute('data-text') || '';
              c.style.display = t.includes(term) ? '' : 'none';
            });
          }
          q.addEventListener('input', apply);
        })();
      </script>
    </div>
    <?php
    exit;
}

if ($section === 'profile') {
    $stmt = $conn->prepare("
        SELECT u.username, u.email, u.phone_number, s.registration_number, s.first_name, s.last_name, c.course_name
        FROM users u
        JOIN students s ON u.user_id = s.student_id
        JOIN courses c ON s.course_id = c.course_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    ?>
    <div class="space-y-6">
      <h2 class="text-xl md:text-2xl font-semibold text-gray-900">Profile</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Name</div>
          <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')); ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Username</div>
          <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($profile['username'] ?? ''); ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Email</div>
          <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($profile['email'] ?? ''); ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Phone</div>
          <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($profile['phone_number'] ?? ''); ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Registration No.</div>
          <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($profile['registration_number'] ?? ''); ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
          <div class="text-sm text-gray-600">Course</div>
          <div class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($profile['course_name'] ?? ''); ?></div>
        </div>
      </div>
    </div>
    <?php
    exit;
}

exit;
