<?php
require_once 'config.php';

class LecturerController {
    private $conn;

    public function __construct($conn) {
        if (!$conn || $conn->connect_error) {
            error_log("Invalid database connection in LecturerController: " . ($conn->connect_error ?? 'Unknown error') . "\n", 3, '../logs/errors.log');
            throw new Exception("Database connection failed.");
        }
        $this->conn = $conn;
    }

    public function getDashboardStats($lecturer_id) {
        $stats = [
            'lecturer_name' => 'Lecturer',
            'staff_number' => 'N/A',
            'assigned_units' => 0,
            'sessions_conducted' => 0,
            'assignments_created' => 0,
            'notifications' => [],
            'recent_activities' => [],
            'attendance_summary' => [],
            'error' => null
        ];

        try {
            // Lecturer details
            $stmt = $this->conn->prepare("
                SELECT l.first_name, l.last_name, l.staff_number 
                FROM lecturers l
                WHERE l.lecturer_id = ?
            ");
            $stmt->bind_param("i", $lecturer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($lecturer = $result->fetch_assoc()) {
                $stats['lecturer_name'] = trim(($lecturer['first_name'] ?? '') . ' ' . ($lecturer['last_name'] ?? '')) ?: 'Lecturer';
                $stats['staff_number'] = $lecturer['staff_number'] ?: 'N/A';
            } else {
                throw new Exception("Lecturer record not found.");
            }
            $stmt->close();

            // Combined stats
            $stmt = $this->conn->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM lecturer_assignments WHERE lecturer_id = ?) AS assigned_units,
                    (SELECT COUNT(*) FROM class_sessions WHERE lecturer_id = ?) AS sessions_conducted,
                    (SELECT COUNT(*) FROM assignments WHERE created_by = ?) AS assignments_created
            ");
            $stmt->bind_param("iii", $lecturer_id, $lecturer_id, $lecturer_id);
            $stmt->execute();
            $stmt->bind_result($stats['assigned_units'], $stats['sessions_conducted'], $stats['assignments_created']);
            $stmt->fetch();
            $stmt->close();

            // Notifications
            $stmt = $this->conn->prepare("
                SELECT notification_id, message, created_at, is_read
                FROM notifications
                WHERE (user_id = ? OR user_id IS NULL) AND user_type = 'lecturer'
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->bind_param("ii", $lecturer_id, NOTIFICATIONS_LIMIT);
            $stmt->execute();
            $stats['notifications'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Recent activities
            $stmt = $this->conn->prepare("
                SELECT 'session' AS type, session_date AS activity_date, venue AS details
                FROM class_sessions WHERE lecturer_id = ?
                UNION
                SELECT 'assignment' AS type, created_at AS activity_date, title AS details
                FROM assignments WHERE created_by = ?
                ORDER BY activity_date DESC LIMIT 5
            ");
            $stmt->bind_param("ii", $lecturer_id, $lecturer_id);
            $stmt->execute();
            $stats['recent_activities'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Attendance summary
            $stmt = $this->conn->prepare("
                SELECT cs.session_date, cs.venue,
                       COUNT(CASE WHEN ar.status = 'Present' THEN 1 END) AS present,
                       COUNT(ar.attendance_id) AS total
                FROM class_sessions cs
                LEFT JOIN attendance_records ar ON cs.session_id = ar.session_id
                WHERE cs.lecturer_id = ?
                GROUP BY cs.session_id
                ORDER BY cs.session_date DESC LIMIT 5
            ");
            $stmt->bind_param("i", $lecturer_id);
            $stmt->execute();
            $stats['attendance_summary'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $stats;
        } catch (Exception $e) {
            error_log("Error in getDashboardStats: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            $stats['error'] = "An error occurred while fetching dashboard stats.";
            return $stats;
        }
    }

    public function scheduleSession($lecturer_id, $assignment_id, $session_date, $venue, $start_time, $end_time) {
        try {
            // Sanitize inputs
            $session_date = htmlspecialchars(trim($session_date), ENT_QUOTES, 'UTF-8');
            $venue = htmlspecialchars(trim($venue), ENT_QUOTES, 'UTF-8');
            $start_time = htmlspecialchars(trim($start_time), ENT_QUOTES, 'UTF-8');
            $end_time = htmlspecialchars(trim($end_time), ENT_QUOTES, 'UTF-8');

            // Validate inputs
            if (!strtotime($session_date) || !strtotime($start_time) || !strtotime($end_time)) {
                throw new Exception("Invalid date or time format.");
            }
            if (strtotime($start_time) >= strtotime($end_time)) {
                throw new Exception("Start time must be before end time.");
            }
            if (empty($venue)) {
                throw new Exception("Venue is required.");
            }

            // Verify assignment
            $stmt = $this->conn->prepare("SELECT unit_id FROM lecturer_assignments WHERE assignment_id = ? AND lecturer_id = ?");
            $stmt->bind_param("ii", $assignment_id, $lecturer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to verify assignment.");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid assignment selected.");
            }
            $unit_id = $result->fetch_assoc()['unit_id'];
            $stmt->close();

            // Insert session
            $stmt = $this->conn->prepare("
                INSERT INTO class_sessions (unit_id, lecturer_id, session_date, start_time, end_time, venue)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissss", $unit_id, $lecturer_id, $session_date, $start_time, $end_time, $venue);
            if (!$stmt->execute()) {
                throw new Exception("Failed to schedule session: " . $stmt->error);
            }
            $stmt->close();

            return ['success' => true, 'message' => 'Class session scheduled successfully.'];
        } catch (Exception $e) {
            error_log("Error in scheduleSession: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getLecturerAssignments($lecturer_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT la.assignment_id, cu.unit_name, la.academic_year, la.semester 
                FROM lecturer_assignments la
                JOIN course_units cu ON la.unit_id = cu.unit_id
                WHERE la.lecturer_id = ?
                ORDER BY la.academic_year DESC, la.semester DESC, cu.unit_name ASC
            ");
            $stmt->bind_param("i", $lecturer_id);
            $stmt->execute();
            $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return ['success' => true, 'assignments' => $assignments];
        } catch (Exception $e) {
            error_log("Error in getLecturerAssignments: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            return ['success' => false, 'error' => "Failed to fetch assignments: " . $e->getMessage()];
        }
    }

    public function getEnrolledStudents($lecturer_id, $assignment_id) {
        try {
            // Verify assignment
            $stmt = $this->conn->prepare("
                SELECT la.unit_id, la.semester, la.academic_year
                FROM lecturer_assignments la
                WHERE la.assignment_id = ? AND la.lecturer_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $lecturer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to verify assignment.");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid assignment selected.");
            }
            $assignment = $result->fetch_assoc();
            $stmt->close();

            // Fetch students
            $stmt = $this->conn->prepare("
                SELECT s.student_id, s.registration_number, s.first_name, s.last_name, u.email
                FROM student_enrollments se
                JOIN students s ON se.student_id = s.student_id
                JOIN users u ON s.student_id = u.user_id
                WHERE se.unit_id = ? AND se.semester = ? AND se.academic_year = ?
                ORDER BY s.registration_number ASC
            ");
            $stmt->bind_param("iis", $assignment['unit_id'], $assignment['semester'], $assignment['academic_year']);
            $stmt->execute();
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return ['success' => true, 'students' => $students];
        } catch (Exception $e) {
            error_log("Error in getEnrolledStudents: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getClassSessions($lecturer_id, $session_id = null) {
        try {
            $query = "
                SELECT 
                    cs.session_id,
                    cs.unit_id,
                    cs.lecturer_id,
                    cs.session_date,
                    cs.start_time,
                    cs.end_time,
                    cs.venue,
                    cs.session_topic,
                    cu.unit_name,
                    la.assignment_id
                FROM class_sessions cs
                JOIN course_units cu ON cs.unit_id = cu.unit_id
                JOIN lecturer_assignments la ON cs.unit_id = la.unit_id AND cs.lecturer_id = la.lecturer_id
                WHERE cs.lecturer_id = ?
            ";
            $params = [$lecturer_id];
            $types = "i";

            if ($session_id !== null) {
                $query .= " AND cs.session_id = ?";
                $params[] = $session_id;
                $types .= "i";
            }

            $query .= " ORDER BY cs.session_date DESC, cs.start_time DESC";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $sessions = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return ['success' => true, 'sessions' => $sessions];
        } catch (Exception $e) {
            error_log("Error in getClassSessions: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getSessionStudents($lecturer_id, $assignment_id, $session_id) {
        try {
            // Verify assignment
            $stmt = $this->conn->prepare("
                SELECT la.unit_id, la.semester, la.academic_year
                FROM lecturer_assignments la
                WHERE la.assignment_id = ? AND la.lecturer_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $lecturer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to verify assignment_id.");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid assignment_id selected.");
            }
            $assignment = $result->fetch_assoc();
            $stmt->close();

            // Verify session
            $stmt = $this->conn->prepare("
                SELECT session_id
                FROM class_sessions
                WHERE session_id = ? AND unit_id = ? AND lecturer_id = ?
            ");
            $stmt->bind_param("iii", $session_id, $assignment['unit_id'], $lecturer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to verify session_id.");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid session_id selected.");
            }
            $stmt->close();

            // Fetch students
            $stmt = $this->conn->prepare("
                SELECT s.student_id, s.registration_number, s.first_name, s.last_name, u.email, ar.status AS attendance_status
                FROM student_enrollments se
                JOIN students s ON se.student_id = s.student_id
                JOIN users u ON s.student_id = u.user_id
                LEFT JOIN attendance_records ar ON s.student_id = ar.student_id AND ar.session_id = ?
                WHERE se.unit_id = ? AND se.semester = ? AND se.academic_year = ?
                ORDER BY s.registration_number ASC
            ");
            $stmt->bind_param("iiis", $session_id, $assignment['unit_id'], $assignment['semester'], $assignment['academic_year']);
            $stmt->execute();
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return ['success' => true, 'students' => $students];
        } catch (Exception $e) {
            error_log("Error in getSessionStudents: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            return ['success' => false, 'error' => "Failed to fetch students: " . $e->getMessage()];
        }
    }

    public function saveAttendance($lecturer_id, $session_id, $assignment_id, $attendance_data) {
        try {
            // Verify assignment
            $stmt = $this->conn->prepare("
                SELECT la.unit_id
                FROM lecturer_assignments la
                WHERE la.assignment_id = ? AND la.lecturer_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $lecturer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to verify assignment_id.");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid assignment_id selected.");
            }
            $unit_id = $result->fetch_assoc()['unit_id'];
            $stmt->close();

            // Verify session
            $stmt = $this->conn->prepare("
                SELECT session_id
                FROM class_sessions
                WHERE session_id = ? AND unit_id = ? AND lecturer_id = ?
            ");
            $stmt->bind_param("iii", $session_id, $unit_id, $lecturer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to verify session_id.");
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid session_id selected.");
            }
            $stmt->close();

            // Begin transaction
            $this->conn->begin_transaction();

            foreach ($attendance_data as $student_id => $status) {
                $student_id = (int)$student_id;
                $status = in_array($status, ['Present', 'Absent', 'Late']) ? $status : 'Absent';

                // Check if record exists
                $stmt = $this->conn->prepare("
                    SELECT attendance_id
                    FROM attendance_records
                    WHERE session_id = ? AND student_id = ?
                ");
                $stmt->bind_param("ii", $session_id, $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result->num_rows > 0;
                $stmt->close();

                if ($exists) {
                    $stmt = $this->conn->prepare("
                        UPDATE attendance_records
                        SET status = ?, marked_at = NOW()
                        WHERE session_id = ? AND student_id = ?
                    ");
                    $stmt->bind_param("sii", $status, $session_id, $student_id);
                } else {
                    $stmt = $this->conn->prepare("
                        INSERT INTO attendance_records (session_id, student_id, status, marked_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("iis", $session_id, $student_id, $status);
                }

                if (!$stmt->execute()) {
                    throw new Exception("Error saving attendance for student_id $student_id: " . $stmt->error);
                }
                $stmt->close();
            }

            // Commit transaction
            $this->conn->commit();
            return ['success' => true, 'message' => 'Attendance saved successfully.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error in saveAttendance: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
            return ['success' => false, 'error' => "Failed to save attendance: " . $e->getMessage()];
        }
    }
}
?>