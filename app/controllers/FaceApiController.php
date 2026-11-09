<?php

/**
 * Face recognition attendance for teachers, admins and staff.
 *
 *  - The browser finds the face and turns it into 128 numbers (a descriptor).
 *  - Matching happens HERE, so the stored templates are never sent to a
 *    kiosk. Photos are never uploaded or stored.
 *
 * Public:  POST /api/face/scan            (the face kiosk, no login)
 * Admin:   GET  /api/admin/face           (who is enrolled)
 *          POST /api/admin/face/enroll    (save a person's face samples)
 *          POST /api/admin/face/delete    (remove a person's face)
 */
class FaceApiController extends Controller
{
    /** Lower is stricter. Same person is usually 0.2 - 0.4 apart; different people 0.5+. */
    private const MATCH_THRESHOLD = 0.45;

    /** A face already enrolled for another account is rejected. */
    private const DUPLICATE_THRESHOLD = 0.40;

    /** Ignore a second recognition of the same person within N seconds. */
    private const DEBOUNCE_SECONDS = 30;

    private const MIN_SAMPLES = 3;
    private const MAX_SAMPLES = 10;

    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
        $this->call->model('UserModel');
        $this->call->model('FaceModel');
    }

    /* =========================================================
       PUBLIC: POST /api/face/scan  { descriptor: [128 numbers] }
       ========================================================= */

    public function scan()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit(
            'face_scan_' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            40,
            60
        );

        $descriptor = $this->cleanDescriptor($this->api->body()['descriptor'] ?? null);

        if ($descriptor === null) {
            $this->api->respond_error('The camera did not send a valid face.', 422);
            return;
        }

        $this->FaceModel->ensure_table();

        $match = $this->bestMatch($descriptor, $this->FaceModel->all_templates());

        if ($match === null || $match['distance'] > self::MATCH_THRESHOLD) {
            $this->api->respond_error('Face not recognized. Please look at the camera or ask the administrator to enroll your face.', 404);
            return;
        }

        $user = $this->UserModel->find_by('user_id', $match['user_id']);

        if (
            !is_array($user) ||
            !in_array(strtolower((string) ($user['role'] ?? '')), ['teacher', 'admin', 'staff'], true)
        ) {
            $this->api->respond_error('This face belongs to an account that cannot use the camera attendance.', 403);
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $stamp = $now->format('Y-m-d H:i:s');
        $role = strtolower((string) $user['role']);
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        $last = $this->FaceModel->last_gate_event((int) $user['user_id']);

        if (
            is_array($last) &&
            strtotime($stamp) - strtotime((string) ($last['scanned_at'] ?? '')) < self::DEBOUNCE_SECONDS
        ) {
            $this->api->respond_error($name . ' was just recorded. Please wait a moment.', 429);
            return;
        }

        $sameDay = is_array($last)
            && substr((string) ($last['scanned_at'] ?? ''), 0, 10) === $now->format('Y-m-d');
        $event = ($sameDay && strtoupper((string) ($last['event_type'] ?? '')) === 'IN') ? 'OUT' : 'IN';

        $saved = $this->FaceModel->add_gate_event([
            'user_id' => (int) $user['user_id'],
            'role' => $role,
            'rfid_uid' => 'FACE',
            'event_type' => $event,
            'scanned_at' => $stamp,
            'device_name' => 'Face Camera',
        ]);

        if ($saved === false) {
            $this->api->respond_error('Attendance could not be saved.', 500);
            return;
        }

        $this->api->respond([
            'success' => true,
            'name' => $name,
            'role' => ucfirst($role === 'admin' ? 'staff' : $role),
            'status' => $event === 'IN' ? 'Checked In' : 'Checked Out',
            'event_type' => $event,
            'time' => $stamp,
            'message' => $name . ($event === 'IN' ? ' checked in.' : ' checked out.'),
        ]);
    }

    /* =========================================================
       ADMIN: GET /api/admin/face
       ========================================================= */

    public function admin_list()
    {
        $this->api->require_method('GET');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $this->FaceModel->ensure_table();

        $templates = [];

        foreach ($this->FaceModel->all_templates() as $row) {
            $templates[(int) $row['user_id']] = $row;
        }

        $people = [];
        $users = $this->db->table('users')->get_all();

        foreach (is_array($users) ? $users : [] as $user) {
            $role = strtolower((string) ($user['role'] ?? ''));

            if (!in_array($role, ['teacher', 'admin', 'staff'], true) || !empty($user['deleted_at'])) {
                continue;
            }

            $id = (int) $user['user_id'];
            $template = $templates[$id] ?? null;

            $people[] = [
                'user_id' => $id,
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role' => $role,
                'enrolled' => $template !== null,
                'sample_count' => $template ? (int) $template['sample_count'] : 0,
                'updated_at' => $template ? (string) ($template['updated_at'] ?? '') : '',
            ];
        }

        usort($people, static function ($a, $b) {
            return strcasecmp($a['role'] . $a['last_name'] . $a['first_name'], $b['role'] . $b['last_name'] . $b['first_name']);
        });

        $this->api->respond(['success' => true, 'people' => $people]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/face/enroll { user_id, descriptors: [[128],...] }
       ========================================================= */

    public function admin_enroll()
    {
        $this->api->require_method('POST');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $input = $this->api->body();
        $userId = (int) ($input['user_id'] ?? 0);
        $user = $userId > 0 ? $this->UserModel->find_by('user_id', $userId) : null;

        if (
            !is_array($user) ||
            !in_array(strtolower((string) ($user['role'] ?? '')), ['teacher', 'admin', 'staff'], true)
        ) {
            $this->api->respond_error('Choose a teacher, admin, or staff account.', 404);
            return;
        }

        $raw = $input['descriptors'] ?? null;

        if (!is_array($raw) || count($raw) < self::MIN_SAMPLES || count($raw) > self::MAX_SAMPLES) {
            $this->api->respond_error('Capture ' . self::MIN_SAMPLES . ' to ' . self::MAX_SAMPLES . ' face samples.', 422);
            return;
        }

        $samples = [];

        foreach ($raw as $descriptor) {
            $clean = $this->cleanDescriptor($descriptor);

            if ($clean === null) {
                $this->api->respond_error('One of the face samples is invalid. Please capture again.', 422);
                return;
            }

            $samples[] = $clean;
        }

        $this->FaceModel->ensure_table();

        // The same face must not be enrolled under two accounts.
        $others = array_filter($this->FaceModel->all_templates(), static function ($row) use ($userId) {
            return (int) $row['user_id'] !== $userId;
        });

        foreach ($samples as $sample) {
            $match = $this->bestMatch($sample, $others);

            if ($match !== null && $match['distance'] <= self::DUPLICATE_THRESHOLD) {
                $owner = $this->UserModel->find_any('user_id', $match['user_id']);
                $ownerName = is_array($owner)
                    ? trim((string) ($owner['first_name'] ?? '') . ' ' . (string) ($owner['last_name'] ?? ''))
                    : 'another account';

                $this->api->respond_error('This face is already enrolled for ' . $ownerName . '.', 409);
                return;
            }
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        $this->FaceModel->save_template($userId, $samples, $now);

        $this->api->respond([
            'success' => true,
            'message' => 'Face enrolled with ' . count($samples) . ' samples.',
        ]);
    }

    /* =========================================================
       ADMIN: POST /api/admin/face/delete { user_id }
       ========================================================= */

    public function admin_delete()
    {
        $this->api->require_method('POST');

        if (!$this->authenticatedAdmin()) {
            return;
        }

        $userId = (int) ($this->api->body()['user_id'] ?? 0);

        $this->FaceModel->ensure_table();

        if (!$this->FaceModel->template_for_user($userId)) {
            $this->api->respond_error('No enrolled face was found for that account.', 404);
            return;
        }

        $this->FaceModel->delete_template($userId);

        $this->api->respond(['success' => true, 'message' => 'Face removed.']);
    }

    /* =========================================================
       HELPERS
       ========================================================= */

    /** 128 finite numbers, or null. */
    public function cleanDescriptor($value)
    {
        if (!is_array($value) || count($value) !== 128) {
            return null;
        }

        $clean = [];

        foreach ($value as $number) {
            if (!is_numeric($number) || !is_finite((float) $number) || abs((float) $number) > 5) {
                return null;
            }

            $clean[] = round((float) $number, 6);
        }

        return $clean;
    }

    public function distance(array $a, array $b): float
    {
        $sum = 0.0;

        for ($i = 0; $i < 128; $i++) {
            $d = $a[$i] - $b[$i];
            $sum += $d * $d;
        }

        return sqrt($sum);
    }

    /**
     * The enrolled person whose closest sample is nearest to $descriptor.
     *
     * @return array{user_id:int, distance:float}|null
     */
    public function bestMatch(array $descriptor, array $templates)
    {
        $best = null;

        foreach ($templates as $row) {
            $samples = json_decode((string) ($row['descriptors'] ?? ''), true);

            if (!is_array($samples)) {
                continue;
            }

            foreach ($samples as $sample) {
                if (!is_array($sample) || count($sample) !== 128) {
                    continue;
                }

                $distance = $this->distance($descriptor, $sample);

                if ($best === null || $distance < $best['distance']) {
                    $best = ['user_id' => (int) $row['user_id'], 'distance' => $distance];
                }
            }
        }

        return $best;
    }

    private function authenticatedAdmin(): ?array
    {
        $token = $this->api->get_bearer_token();
        $payload = $token ? $this->api->validate_jwt($token) : null;
        $subject = is_array($payload) ? ($payload['sub'] ?? $payload['id'] ?? null) : null;
        $userId = filter_var($subject, FILTER_VALIDATE_INT);

        if (!$userId || $userId < 1) {
            $this->api->respond_error('Unauthorized. Please sign in again.', 401);
            return null;
        }

        $user = $this->UserModel->find_by('user_id', (int) $userId);

        if (!is_array($user)) {
            $this->api->respond_error('The account associated with this token no longer exists.', 401);
            return null;
        }

        if (strtolower(trim((string) ($user['role'] ?? ''))) !== 'admin') {
            $this->api->respond_error('You do not have permission to access this endpoint.', 403);
            return null;
        }

        return $user;
    }
}
