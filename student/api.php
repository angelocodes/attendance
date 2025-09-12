<?php
// att/student/api.php
include '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    http_response_code(403);
    exit('Unauthorized');
}
$student_id = (int)$_SESSION['user_id'];

function h($s){ return htmlspecialchars($s); }

// helper to bind params dynamically (references)
function bind_params($stmt, $types, $params) {
    if ($types === '') return;
    $refs = [];
    $refs[] = $types;
    foreach ($params as $k => $v) $refs[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// -------------- Handle simple actions first ----------------
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'count_notifications') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 AND user_type = 'student'");
        $stmt->bind_param("i",$student_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        echo (int)$count;
        exit;
    }

    if ($action === 'get_preferences') {
        $stmt = $conn->prepare("SELECT setting_value FROM user_preferences WHERE user_id = ? AND widget = 'student_prefs' LIMIT 1");
        $stmt->bind_param("i",$student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo $row['setting_value'];
            exit;
        } else {
            echo json_encode([]);
            exit;
        }
    }

    if ($action === 'save_preferences') {
        $theme = $_POST['theme'] ?? 'auto';
        $notif = $_POST['notif'] ?? 'portal';
        $prefs = json_encode(['theme'=>$theme,'notif'=>$notif]);
        $stmt = $conn->prepare("INSERT INTO user_preferences (user_id, widget, setting_value) VALUES (?, 'student_prefs', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param("is",$student_id, $prefs);
        $stmt->execute();
        echo 'ok';
        exit;
    }

    if ($action === 'download_csv') {
        // implement CSV export for attendance using the same filter logic as below
        $q = trim($_POST['q'] ?? '');
        // Build base query
        $base = "SELECT u.username, s.registration_number, cu.unit_code, cu.unit_name, ar.status, ar.marked_at
                 FROM attendance_records ar
                 JOIN users u ON ar.student_id = u.user_id
                 JOIN students s ON ar.student_id = s.student_id
                 JOIN class_sessions cs ON ar.session_id = cs.session_id
                 JOIN course_units cu ON cs.unit_id = cu.unit_id
                 WHERE ar.student_id = ?";
        $params = [$student_id];
        $types = "i";

        if ($q !== '') {
            $base .= " AND (cu.unit_name LIKE ? OR cu.unit_code LIKE ? OR ar.marked_at LIKE ?)";
            $like = "%$q%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= "sss";
        }
        $base .= " ORDER BY ar.marked_at DESC";

        $stmt = $conn->prepare($base);
        bind_params($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="attendance_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Username','Reg No','Unit Code','Unit Name','Status','Date']);
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [$r['username'],$r['registration_number'],$r['unit_code'],$r['unit_name'],$r['status'],date('Y-m-d H:i', strtotime($r['marked_at']))]);
        }
        fclose($out);
        exit;
    }
}

// -------------- Section rendering ----------------
if (isset($_POST['section'])) {
    $section = $_POST['section'];

    // 1) Overview
    if ($section === 'overview') {
        // enrolled units count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM student_enrollments WHERE student_id = ?");
        $stmt->bind_param("i",$student_id);
        $stmt->execute();
        $stmt->bind_result($enrolled_units); $stmt->fetch(); $stmt->close();

        // attendance rate
        $stmt = $conn->prepare("SELECT SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present, COUNT(*) AS total FROM attendance_records WHERE student_id = ?");
        $stmt->bind_param("i",$student_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $present = (int)($res['present'] ?? 0);
        $total = (int)($res['total'] ?? 0);
        $attendance_rate = $total ? round(($present/$total)*100,2) : 0;

        // upcoming sessions next 3
        $stmt = $conn->prepare("
            SELECT cs.session_date, cs.start_time, cs.end_time, cu.unit_name, cs.venue
            FROM class_sessions cs
            JOIN course_units cu ON cs.unit_id = cu.unit_id
            JOIN student_enrollments se ON cu.unit_id = se.unit_id
            WHERE se.student_id = ? AND cs.session_date >= CURDATE()
            ORDER BY cs.session_date ASC, cs.start_time ASC
            LIMIT 3
        ");
        $stmt->bind_param("i",$student_id);
        $stmt->execute(); $upcoming = $stmt->get_result();

        // recent attendance (5)
        $stmt = $conn->prepare("
            SELECT ar.status, cs.session_date, cu.unit_name 
            FROM attendance_records ar 
            JOIN class_sessions cs ON ar.session_id = cs.session_id 
            JOIN course_units cu ON cs.unit_id = cu.unit_id 
            WHERE ar.student_id = ?
            ORDER BY cs.session_date DESC
            LIMIT 5
        ");
        $stmt->bind_param("i",$student_id);
        $stmt->execute(); $recent_att = $stmt->get_result();

        // recent notifications (3)
        $stmt = $conn->prepare("
            SELECT n.message, n.created_at, cu.unit_name 
            FROM notifications n 
            LEFT JOIN course_units cu ON n.unit_id = cu.unit_id 
            WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'student'
            ORDER BY n.created_at DESC
            LIMIT 3
        ");
        $stmt->bind_param("i",$student_id);
        $stmt->execute(); $recent_notif = $stmt->get_result();

        ob_start();
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-white p-4 rounded shadow">
            <h3 class="text-sm font-semibold text-gray-500">Enrolled Units</h3>
            <div class="mt-2 flex items-center justify-between">
              <div>
                <p class="text-2xl font-bold text-sky-600"><?= h($enrolled_units) ?></p>
                <p class="text-sm text-gray-500">Active this semester</p>
              </div>
              <div class="text-4xl text-gray-200"><i class="fa fa-book"></i></div>
            </div>
          </div>

          <div class="bg-white p-4 rounded shadow">
            <h3 class="text-sm font-semibold text-gray-500">Overall Attendance</h3>
            <div class="mt-3">
              <div class="w-full bg-gray-200 rounded h-3 overflow-hidden">
                <div style="width:<?= $attendance_rate ?>%" class="h-3 bg-emerald-400"></div>
              </div>
              <p class="mt-2 text-sm font-medium"><?= $attendance_rate ?>% present</p>
            </div>
          </div>

          <div class="bg-white p-4 rounded shadow">
            <h3 class="text-sm font-semibold text-gray-500">Upcoming Sessions</h3>
            <div class="mt-2">
              <?php if ($upcoming->num_rows): ?>
                <ul class="space-y-2">
                  <?php while($r = $upcoming->fetch_assoc()): ?>
                    <li class="p-2 border rounded bg-gray-50 text-sm">
                      <strong><?= h($r['session_date']) ?></strong> • <?= h($r['unit_name']) ?> • <?= h($r['start_time']) ?> — <?= h($r['end_time']) ?> <span class="text-gray-500">(@ <?= h($r['venue']) ?>)</span>
                    </li>
                  <?php endwhile; ?>
                </ul>
              <?php else: ?>
                <p class="text-sm text-gray-500">No upcoming sessions</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold text-gray-700 mb-2">Recent Attendance</h4>
            <?php if ($recent_att->num_rows): ?>
              <ul class="space-y-2 text-sm">
                <?php while($r = $recent_att->fetch_assoc()):
                  $cls = $r['status'] === 'Present' ? 'text-green-600' : ($r['status'] === 'Late' ? 'text-yellow-600' : 'text-red-600');
                ?>
                  <li><span class="<?= $cls ?> font-bold"><?= h($r['status']) ?></span> — <?= h($r['unit_name']) ?> (<?= h($r['session_date']) ?>)</li>
                <?php endwhile; ?>
              </ul>
            <?php else: ?>
              <p class="text-sm text-gray-500">No recent attendance</p>
            <?php endif; ?>
          </div>

          <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold text-gray-700 mb-2">Recent Notifications</h4>
            <?php if ($recent_notif->num_rows): ?>
              <ul class="space-y-2 text-sm">
                <?php while($n = $recent_notif->fetch_assoc()): ?>
                  <li class="p-2 border rounded bg-gray-50">
                    <?= h($n['message']) ?> <div class="text-xs text-gray-400 mt-1"><?= h($n['created_at']) ?> <?= $n['unit_name'] ? ' • '.h($n['unit_name']) : '' ?></div>
                  </li>
                <?php endwhile; ?>
              </ul>
            <?php else: ?>
              <p class="text-sm text-gray-500">No notifications</p>
            <?php endif; ?>
          </div>
        </div>
        <?php
        echo ob_get_clean();
        exit;
    }

    // 2) Enrolled units
    if ($section === 'enrolled_units') {
        $stmt = $conn->prepare("SELECT cu.unit_id, cu.unit_name, cu.unit_code FROM student_enrollments se JOIN course_units cu ON se.unit_id = cu.unit_id WHERE se.student_id = ?");
        $stmt->bind_param("i",$student_id); $stmt->execute(); $res = $stmt->get_result();
        ob_start();
        if ($res->num_rows) {
            echo '<ul class="space-y-2">';
            while($r = $res->fetch_assoc()){
                echo '<li class="p-3 border rounded flex justify-between items-center">';
                echo '<div><strong class="text-sm text-sky-600">'.h($r['unit_name']).'</strong><div class="text-xs text-gray-500">'.h($r['unit_code']).'</div></div>';
                echo '<div><button class="text-sm text-gray-500" onclick="loadSection(\'unit_sessions?unit_id='.$r['unit_id'].'\')">View sessions</button></div>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="text-sm text-gray-500">You are not enrolled in any units.</p>';
        }
        echo ob_get_clean();
        exit;
    }

    // 3) Unit sessions (render sessions for a given unit)
    if (strpos($section, 'unit_sessions') === 0) {
        // parse query string appended to section e.g. unit_sessions?unit_id=3
        parse_str(parse_url($section, PHP_URL_QUERY), $params);
        if (!isset($params['unit_id']) || !is_numeric($params['unit_id'])) {
            echo '<p class="text-red-600">Invalid unit id.</p>'; exit;
        }
        $unit_id = (int)$params['unit_id'];
        $stmt = $conn->prepare("SELECT session_id, session_date, start_time, end_time, venue, session_topic FROM class_sessions WHERE unit_id = ? ORDER BY session_date DESC");
        $stmt->bind_param("i",$unit_id); $stmt->execute(); $res = $stmt->get_result();
        ob_start();
        echo '<h3 class="font-semibold mb-2">Sessions</h3>';
        if ($res->num_rows) {
            echo '<table class="w-full text-sm"><thead><tr class="text-left text-xs text-gray-500"><th>Date</th><th>Time</th><th>Venue</th><th>Topic</th></tr></thead><tbody>';
            while($r = $res->fetch_assoc()){
                echo '<tr class="border-b"><td class="py-2">'.h($r['session_date']).'</td><td class="py-2">'.h($r['start_time'].' - '.$r['end_time']).'</td><td class="py-2">'.h($r['venue']).'</td><td class="py-2">'.h($r['session_topic']).'</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="text-sm text-gray-500">No sessions for this unit.</p>';
        }
        echo ob_get_clean();
        exit;
    }

    // 4) Timetable (next 7 days)
    if ($section === 'timetable') {
        $stmt = $conn->prepare("
            SELECT cs.session_id, cs.session_date, cs.start_time, cs.end_time, cs.venue, cu.unit_name
            FROM class_sessions cs
            JOIN course_units cu ON cs.unit_id = cu.unit_id
            JOIN student_enrollments se ON cu.unit_id = se.unit_id
            WHERE se.student_id = ? AND cs.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY cs.session_date, cs.start_time
        ");
        $stmt->bind_param("i",$student_id); $stmt->execute(); $res = $stmt->get_result();
        ob_start();
        if ($res->num_rows) {
            echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
            while($r = $res->fetch_assoc()){
                echo '<article class="p-3 border rounded bg-white">';
                echo '<div class="flex justify-between items-start">';
                echo '<div><div class="text-sm text-gray-500">'.h($r['session_date']).' • '.h($r['start_time']).' - '.h($r['end_time']).'</div>';
                echo '<div class="font-semibold text-sky-600">'.h($r['unit_name']).'</div>';
                echo '<div class="text-xs text-gray-500 mt-1">Venue: '.h($r['venue']).'</div></div>';
                echo '<div class="text-2xl text-gray-200"><i class="fa fa-chalkboard"></i></div>';
                echo '</div></article>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-sm text-gray-500">No sessions scheduled in the next 7 days.</p>';
        }
        echo ob_get_clean();
        exit;
    }

    // 5) Attendance listing (with optional search/filter parameters)
    if ($section === 'attendance') {
        $q = trim($_POST['q'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $unit_id = isset($_POST['unit_id']) && is_numeric($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
        $academic_year_id = isset($_POST['academic_year_id']) && is_numeric($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : 0;
        $semester = isset($_POST['semester']) && is_numeric($_POST['semester']) ? (int)$_POST['semester'] : 0;

        $sql = "SELECT cu.unit_name, cu.unit_code, ar.status, cs.session_date, cs.session_id, ar.marked_at
                FROM attendance_records ar
                JOIN class_sessions cs ON ar.session_id = cs.session_id
                JOIN course_units cu ON cs.unit_id = cu.unit_id
                WHERE ar.student_id = ?";
        $params = [$student_id];
        $types = "i";

        if ($q !== '') {
            $sql .= " AND (cu.unit_name LIKE ? OR cu.unit_code LIKE ? OR ar.status LIKE ? OR cs.session_date LIKE ?)";
            $like = "%$q%";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= "ssss";
        }
        if ($unit_id) {
            $sql .= " AND cu.unit_id = ?";
            $params[] = $unit_id; $types .= "i";
        }
        if ($start_date && $end_date) {
            $sql .= " AND ar.marked_at BETWEEN ? AND ?";
            $params[] = $start_date . ' 00:00:00'; $params[] = $end_date . ' 23:59:59'; $types .= "ss";
        }
        // academic_year and semester filtering require student_enrollments or field linking; skip if not applicable here

        $sql .= " ORDER BY cs.session_date DESC, ar.marked_at DESC LIMIT 1000";

        $stmt = $conn->prepare($sql);
        bind_params($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();

        ob_start();
        if ($res->num_rows) {
            echo '<table class="w-full text-sm"><thead><tr class="text-xs text-gray-500"><th>Date</th><th>Unit</th><th>Status</th><th>Marked At</th></tr></thead><tbody>';
            while($r = $res->fetch_assoc()){
                $cls = $r['status'] === 'Present' ? 'bg-emerald-100 text-emerald-700' : ($r['status']==='Late' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-700');
                echo '<tr class="border-b">';
                echo '<td class="py-2">'.h($r['session_date']).'</td>';
                echo '<td class="py-2">'.h($r['unit_name']).' <span class="text-xs text-gray-400">('.h($r['unit_code']).')</span></td>';
                echo '<td class="py-2"><span class="px-2 py-1 rounded text-xs '.$cls.'">'.h($r['status']).'</span></td>';
                echo '<td class="py-2">'.h($r['marked_at']).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="text-sm text-gray-500">No attendance records found.</p>';
        }
        echo ob_get_clean();
        exit;
    }

    // 6) Statistics (unit-level and overall)
    if ($section === 'statistics') {
        $stmt = $conn->prepare("
            SELECT cu.unit_id, cu.unit_name,
                SUM(CASE WHEN ar.status='Present' THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN ar.status='Absent' THEN 1 ELSE 0 END) AS absent,
                SUM(CASE WHEN ar.status='Late' THEN 1 ELSE 0 END) AS late,
                COUNT(*) AS total
            FROM attendance_records ar
            JOIN class_sessions cs ON ar.session_id = cs.session_id
            JOIN course_units cu ON cs.unit_id = cu.unit_id
            WHERE ar.student_id = ?
            GROUP BY cu.unit_id
            ORDER BY cu.unit_name
        ");
        $stmt->bind_param("i",$student_id); $stmt->execute(); $res = $stmt->get_result();
        $units = $res->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare("SELECT SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present, SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent, SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS late FROM attendance_records WHERE student_id = ?");
        $stmt->bind_param("i",$student_id); $stmt->execute(); $tot = $stmt->get_result()->fetch_assoc();

        ob_start();
        echo '<div class="col-span-2 grid grid-cols-1 gap-3">';
        foreach($units as $u){
            $percent = $u['total'] ? round(($u['present']/$u['total'])*100,2) : 0;
            echo '<div class="bg-white p-3 rounded shadow">';
            echo '<div class="flex justify-between"><div><div class="font-semibold text-sky-600">'.h($u['unit_name']).'</div><div class="text-xs text-gray-500">'.h($u['present']).' present • '.h($u['absent']).' absent • '.h($u['late']).' late</div></div><div class="text-sm text-gray-400">'.$percent.'%</div></div>';
            echo '<div class="mt-2 w-full bg-gray-200 h-2 rounded"><div class="h-2 bg-emerald-400" style="width:'.$percent.'%"></div></div>';
            echo '</div>';
        }
        echo '</div>';

        $chartData = json_encode([
            'labels' => ['Present','Absent','Late'],
            'values' => [(int)$tot['present'], (int)$tot['absent'], (int)$tot['late']]
        ]);
        // small inline script for pie chart placeholder (dashboard page will embed Chart.js)
        echo '<script>
            (function(){
              const container = document.getElementById("analytics_container");
              container.innerHTML = `<div class="bg-white p-4 rounded shadow"><canvas id="studPieChart"></canvas></div>`;
              const cfg = '.$chartData.';
              const ctx = document.getElementById("studPieChart").getContext("2d");
              new Chart(ctx, { type: "pie", data: { labels: cfg.labels, datasets:[{ data: cfg.values }] }, options:{responsive:true, plugins:{legend:{position:"bottom"}}}});
            })();
        </script>';
        echo ob_get_clean();
        exit;
    }

    // 7) Notifications (searchable) and mark as read
    if ($section === 'notifications') {
        $q = trim($_POST['q'] ?? '');
        $sql = "SELECT n.notification_id, n.message, n.created_at, cu.unit_name FROM notifications n LEFT JOIN course_units cu ON n.unit_id = cu.unit_id WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'student'";
        $params = [$student_id]; $types = "i";

        if ($q !== '') {
            $sql .= " AND (n.message LIKE ? OR cu.unit_name LIKE ?)";
            $like = "%$q%";
            $params[] = $like; $params[] = $like;
            $types .= "ss";
        }
        $sql .= " ORDER BY n.created_at DESC LIMIT 200";
        $stmt = $conn->prepare($sql);
        bind_params($stmt, $types, $params);
        $stmt->execute(); $res = $stmt->get_result();

        ob_start();
        if ($res->num_rows) {
            echo '<ul class="space-y-2">';
            while($n = $res->fetch_assoc()){
                echo '<li class="p-3 border rounded bg-white">';
                echo '<div class="flex justify-between items-start">';
                echo '<div><div class="text-sm">'.h($n['message']).'</div><div class="text-xs text-gray-400 mt-1">'.h($n['created_at']). ($n['unit_name'] ? ' • '.h($n['unit_name']) : '') .'</div></div>';
                echo '</div></li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="text-sm text-gray-500">No notifications found.</p>';
        }
        echo ob_get_clean();

        // mark as read
        $u = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 AND user_type = 'student'");
        $u->bind_param("i",$student_id);
        $u->execute();

        exit;
    }

    // 8) Profile view (returns HTML snippet)
    if ($section === 'profile') {
        $stmt = $conn->prepare("SELECT u.username, u.email, u.phone_number, s.registration_number, s.first_name, s.last_name, s.course_id, c.course_name FROM users u JOIN students s ON u.user_id = s.student_id LEFT JOIN courses c ON s.course_id = c.course_id WHERE u.user_id = ?");
        $stmt->bind_param("i",$student_id); $stmt->execute(); $profile = $stmt->get_result()->fetch_assoc();

        ob_start();
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold text-sky-600 mb-2">Personal Info</h4>
            <form id="profileForm" onsubmit="saveProfile(event)">
              <div class="mb-2">
                <label class="text-sm text-gray-600">First name</label>
                <input name="first_name" value="<?= h($profile['first_name'] ?? '') ?>" class="w-full p-2 border rounded" />
              </div>
              <div class="mb-2">
                <label class="text-sm text-gray-600">Last name</label>
                <input name="last_name" value="<?= h($profile['last_name'] ?? '') ?>" class="w-full p-2 border rounded" />
              </div>
              <div class="mb-2">
                <label class="text-sm text-gray-600">Email</label>
                <input name="email" value="<?= h($profile['email'] ?? '') ?>" class="w-full p-2 border rounded" />
              </div>
              <div class="mb-2">
                <label class="text-sm text-gray-600">Phone</label>
                <input name="phone_number" value="<?= h($profile['phone_number'] ?? '') ?>" class="w-full p-2 border rounded" />
              </div>
              <div class="flex gap-2 mt-3">
                <button class="px-3 py-2 bg-sky-600 text-white rounded">Save profile</button>
                <button type="button" onclick="showPasswordChange()" class="px-3 py-2 border rounded">Change password</button>
              </div>
            </form>
          </div>

          <div class="bg-white p-4 rounded shadow">
            <h4 class="font-semibold text-sky-600 mb-2">Student Details</h4>
            <p class="text-sm"><strong>Registration No:</strong> <?= h($profile['registration_number'] ?? 'N/A') ?></p>
            <p class="text-sm"><strong>Course:</strong> <?= h($profile['course_name'] ?? 'N/A') ?></p>

            <div id="pwdChange" class="mt-4 hidden">
              <h5 class="font-semibold">Change Password</h5>
              <form id="pwdForm" onsubmit="changePassword(event)">
                <div class="mb-2"><input name="current_password" placeholder="Current password" type="password" class="w-full p-2 border rounded"/></div>
                <div class="mb-2"><input name="new_password" placeholder="New password" type="password" class="w-full p-2 border rounded"/></div>
                <div class="mb-2"><input name="confirm_password" placeholder="Confirm new password" type="password" class="w-full p-2 border rounded"/></div>
                <div class="flex gap-2"><button class="px-3 py-2 bg-emerald-500 text-white rounded">Update password</button><button type="button" onclick="hidePasswordChange()" class="px-3 py-2 border rounded">Cancel</button></div>
              </form>
            </div>
          </div>
        </div>

        <script>
          function showPasswordChange(){ document.getElementById('pwdChange').classList.remove('hidden'); }
          function hidePasswordChange(){ document.getElementById('pwdChange').classList.add('hidden'); }

          function saveProfile(e){
            e.preventDefault();
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            data.action = 'update_profile';
            fetch('api.php',{method:'POST', body: new URLSearchParams(data)}).then(r=>r.text()).then(txt=>{
              alert(txt);
              loadSection('profile');
            });
          }
          function changePassword(e){
            e.preventDefault();
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            data.action = 'change_password';
            fetch('api.php',{method:'POST', body: new URLSearchParams(data)}).then(r=>r.text()).then(txt=>{
              alert(txt);
              hidePasswordChange();
            });
          }
        </script>
        <?php
        echo ob_get_clean();
        exit;
    }

} // end section handling

// -------------- Non-section POST actions for profile/password updates etc ----------------
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // update profile
    if ($action === 'update_profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');

        $stmt = $conn->prepare("UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $email, $phone, $student_id); $stmt->execute();

        $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ? WHERE student_id = ?");
        $stmt->bind_param("ssi", $first, $last, $student_id); $stmt->execute();

        echo 'Profile updated';
        exit;
    }

    // change password
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) { echo 'New passwords do not match'; exit; }
        if (strlen($new) < 8) { echo 'Password must be at least 8 characters'; exit; }

        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i",$student_id); $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res || !password_verify($current, $res['password'])) {
            echo 'Current password incorrect'; exit;
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $student_id); $stmt->execute();
        echo 'Password changed';
        exit;
    }
}

// If nothing matched:
http_response_code(400);
echo 'Bad request';
exit;
