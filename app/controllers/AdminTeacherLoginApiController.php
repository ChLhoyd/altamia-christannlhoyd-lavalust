<?php

class AdminTeacherLoginApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
        $this->call->model('UserModel');
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/admin/teacher-logins
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->api->require_method('GET');

        $admin = $this->authenticatedAdmin();

        if (!$admin) {
            return;
        }

        $rows = $this->db->table('teacher_login_logs')
            ->order_by('login_at', 'DESC')
            ->get_all();

        $rows = is_array($rows)
            ? array_values($rows)
            : [];

        $logs = [];

        foreach (array_slice($rows, 0, 200) as $row) {
            $teacherId = (int) ($row['teacher_id'] ?? 0);

            $teacher = $this->UserModel->find_any(
                'user_id',
                $teacherId
            );

            $logs[] = [
                'login_id' => (int) ($row['login_id'] ?? 0),

                'teacher_id' => $teacherId,

                'teacher_name' => is_array($teacher)
                    ? trim(
                        (string) ($teacher['first_name'] ?? '') . ' ' .
                        (string) ($teacher['last_name'] ?? '')
                    )
                    : 'Teacher account removed',

                'email' => (string) ($teacher['email'] ?? ''),

                'login_at' => (string) ($row['login_at'] ?? ''),

                'ip_address' => (string) ($row['ip_address'] ?? ''),
            ];
        }

        $this->api->respond([
            'success' => true,
            'logs' => $logs,
            'total' => count($logs),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN AUTHENTICATION
    |--------------------------------------------------------------------------
    */

    private function authenticatedAdmin(): ?array
    {
        $token = $this->api->get_bearer_token();

        $payload = $token
            ? $this->api->validate_jwt($token)
            : null;

        $subject = is_array($payload)
            ? ($payload['sub'] ?? $payload['id'] ?? null)
            : null;

        $userId = filter_var(
            $subject,
            FILTER_VALIDATE_INT
        );

        if (!$userId || $userId < 1) {
            $this->api->respond_error(
                'Unauthorized. Please sign in again.',
                401
            );
            return null;
        }

        $user = $this->UserModel->find_by(
            'user_id',
            (int) $userId
        );

        if (!is_array($user)) {
            $this->api->respond_error(
                'Administrator account not found.',
                401
            );
            return null;
        }

        if (
            strtolower(trim((string) ($user['role'] ?? ''))) !== 'admin'
        ) {
            $this->api->respond_error(
                'Administrator access is required.',
                403
            );
            return null;
        }

        return $user;
    }
}