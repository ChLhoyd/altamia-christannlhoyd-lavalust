<?php

class Auth extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('session');
        $this->call->model('UserModel');
    }

    public function index()
    {
        $data['error'] = $_SESSION['login_error'] ?? '';

        unset($_SESSION['login_error']);

        $this->call->view('auth/login', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | PHP WEBSITE LOGIN
    |--------------------------------------------------------------------------
    */

    public function login()
    {
        if (!$this->request->is_post()) {
            redirect(site_url('login'));
            return;
        }

        $email = strtolower(trim(
            (string) $this->request->post('email')
        ));

        $password = (string) $this->request->post('password');

        $user = $email !== ''
            ? $this->UserModel->find_by('email', $email)
            : null;

        if (
            !is_array($user) ||
            empty($user['password_hash']) ||
            !password_verify($password, $user['password_hash'])
        ) {
            $_SESSION['login_error'] = 'Invalid email or password.';
            redirect(site_url('login'));
            return;
        }

        $role = strtolower(trim(
            (string) ($user['role'] ?? '')
        ));

        // Admin is now allowed to log in.
        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            $_SESSION['login_error'] = 'This account role is not supported.';
            redirect(site_url('login'));
            return;
        }

        session_regenerate_id(true);

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['email'] = (string) $user['email'];
        $_SESSION['first_name'] = (string) ($user['first_name'] ?? '');
        $_SESSION['last_name'] = (string) ($user['last_name'] ?? '');
        $_SESSION['role'] = $role;

        if ($role === 'teacher') {
            redirect(site_url('teacher/dashboard'));
            return;
        }

        if ($role === 'student') {
            redirect(site_url('student/dashboard'));
            return;
        }

        // Admin uses the React portal. 
        redirect('https://lavalust-react.onrender.com/');
    }

    public function logout()
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        redirect(site_url('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | REACT API LOGIN
    |--------------------------------------------------------------------------
    */

    public function api_login()
    {
        $this->call->library('api');

        $this->api->require_method('POST');

        $input = $this->api->body();

        $email = strtolower(trim(
            (string) ($input['email'] ?? '')
        ));

        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->api->respond_error(
                'Enter your email and password.',
                400
            );
            return;
        }

        $this->api->rate_limit(
            'login_' . $email,
            10,
            60
        );

        $user = $this->UserModel->find_by('email', $email);

        if (
            !is_array($user) ||
            empty($user['password_hash']) ||
            !password_verify($password, $user['password_hash'])
        ) {
            $this->api->respond_error(
                'Invalid email or password.',
                401
            );
            return;
        }

        $role = strtolower(trim(
            (string) ($user['role'] ?? '')
        ));

        // IMPORTANT: accepts admin, teacher, and student.
        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            $this->api->respond_error(
                'This account role is not supported.',
                403
            );
            return;
        }

        /*
         * Save successful teacher logins.
         * If the login-log table has an issue, do not block login.
         */
        if ($role === 'teacher') {
            try {
                $this->db->table('teacher_login_logs')->insert([
                    'teacher_id' => (int) $user['user_id'],

                    'ip_address' => substr(
                        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                        0,
                        45
                    ),

                    'user_agent' => substr(
                        (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                        0,
                        255
                    ),
                ]);
            } catch (Throwable $exception) {
                error_log(
                    'Teacher login log insert failed: ' .
                    $exception->getMessage()
                );
            }
        }

        // Use the actual database primary key, users.user_id.
        $tokens = $this->api->issue_tokens([
            'id' => (int) $user['user_id'],
            'role' => $role,
        ]);

        $this->api->respond([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $this->publicUser($user),
            'tokens' => $tokens,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT LOGGED-IN USER
    |--------------------------------------------------------------------------
    */

    public function api_me()
    {
        $this->call->library('api');

        $this->api->require_method('GET');

        $user = $this->authenticatedApiUser();

        if (!$user) {
            return;
        }

        $this->api->respond([
            'success' => true,
            'user' => $this->publicUser($user),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    public function api_update_profile()
    {
        $this->call->library('api');

        $this->api->require_method('POST');

        $user = $this->authenticatedApiUser();

        if (!$user) {
            return;
        }

        $input = $this->api->body();

        $firstName = trim(
            (string) ($input['first_name'] ?? '')
        );

        $lastName = trim(
            (string) ($input['last_name'] ?? '')
        );

        $email = strtolower(trim(
            (string) ($input['email'] ?? '')
        ));

        $contactNumber = trim(
            (string) ($input['contact_number'] ?? '')
        );

        if (
            $firstName === '' ||
            $lastName === '' ||
            strlen($firstName) > 100 ||
            strlen($lastName) > 100
        ) {
            $this->api->respond_error(
                'Enter a valid first name and last name.',
                422
            );
            return;
        }

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $this->api->respond_error(
                'Enter a valid email address.',
                422
            );
            return;
        }

        $existing = $this->UserModel->find_by(
            'email',
            $email
        );

        if (
            is_array($existing) &&
            (int) $existing['user_id'] !== (int) $user['user_id']
        ) {
            $this->api->respond_error(
                'That email address is already used by another account.',
                409
            );
            return;
        }

        $updated = $this->db->table('users')
            ->where('user_id', (int) $user['user_id'])
            ->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => $contactNumber,
            ]);

        if ($updated === false) {
            $this->api->respond_error(
                'Profile changes could not be saved.',
                500
            );
            return;
        }

        $freshUser = $this->UserModel->find_by(
            'user_id',
            (int) $user['user_id']
        );

        $this->api->respond([
            'success' => true,
            'message' => 'Personal information updated successfully.',
            'user' => $this->publicUser(
                is_array($freshUser) ? $freshUser : $user
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function api_logout()
    {
        $this->call->library('api');

        $this->api->require_method('POST');

        $input = $this->api->body();

        $refreshToken = trim(
            (string) ($input['refresh_token'] ?? '')
        );

        if ($refreshToken !== '') {
            $this->api->revoke_refresh_token($refreshToken);
        }

        $this->api->respond([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REFRESH TOKEN
    |--------------------------------------------------------------------------
    */

    public function api_refresh()
    {
        $this->call->library('api');

        $this->api->require_method('POST');

        $input = $this->api->body();

        $refreshToken = trim(
            (string) ($input['refresh_token'] ?? '')
        );

        if ($refreshToken === '') {
            $this->api->respond_error(
                'Refresh token is required.',
                400
            );
            return;
        }

        $this->api->refresh_access_token($refreshToken);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION HELPER
    |--------------------------------------------------------------------------
    */

    private function authenticatedApiUser(): ?array
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
                'The account associated with this token no longer exists.',
                401
            );
            return null;
        }

        $role = strtolower(trim(
            (string) ($user['role'] ?? '')
        ));

        if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
            $this->api->respond_error(
                'This account role is not supported.',
                403
            );
            return null;
        }

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | SAFE USER RESPONSE
    |--------------------------------------------------------------------------
    */

    private function publicUser(array $user): array
    {
        return [
            'user_id' => (int) ($user['user_id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'role' => strtolower(
                (string) ($user['role'] ?? '')
            ),
            'contact_number' => (string) (
                $user['contact_number'] ?? ''
            ),
        ];
    }
}