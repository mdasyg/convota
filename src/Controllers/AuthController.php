<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Security\Validator;

/**
 * Authentication Controller (Email/Password, Shared Hash, Password Reset)
 */
class AuthController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Show login page with dual tabs (Member Login & Common Organization Key)
     */
    public function login(): void
    {
        if ($this->app->getAuth()->check()) {
            redirect('/dashboard');
        }

        $activeTab = $_GET['tab'] ?? 'member';
        $this->app->render('auth/login', [
            'activeTab' => $activeTab
        ], 'auth_layout');
    }

    /**
     * Process standard user email + password login
     */
    public function processUserLogin(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $res = $this->app->getAuth()->login($email, $password);

        if (!$res['success']) {
            $this->app->setFlash('danger', $res['error']);
            redirect('/login?tab=member');
        }

        $this->app->setFlash('success', "Welcome back, {$res['user']['name']}!");
        redirect('/dashboard');
    }

    /**
     * Process organization login via Common Access Hash
     */
    public function processHashLogin(): void
    {
        $hash = $_GET['hash'] ?? ($_POST['hash'] ?? '');
        if (empty($hash) && !empty($_SERVER['REQUEST_URI'])) {
            if (preg_match('#[?&]hash=([a-zA-Z0-9_-]+)#i', $_SERVER['REQUEST_URI'], $m)) {
                $hash = $m[1];
            } elseif (preg_match('#member_login\.sh&hash=([a-zA-Z0-9_-]+)#i', $_SERVER['REQUEST_URI'], $m)) {
                $hash = $m[1];
            }
        }

        $res = $this->app->getHashAuth()->loginWithHash($hash);

        if (!$res['success']) {
            $this->app->setFlash('danger', $res['error']);
            redirect('/login?tab=hash');
        }

        $orgName = $res['organization']['organization_name'];
        $this->app->setFlash('success', "Authenticated as delegation for '{$orgName}'.");
        redirect('/organizations/portal');
    }


    /**
     * Password reset request (Forgot Password)
     */
    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            if (Validator::isValidEmail($email)) {
                $token = $this->app->getAuth()->generateResetToken($email);
                if ($token) {
                    $user = $this->app->getDb()->fetch("SELECT id, organization_id, name, surname FROM users WHERE LOWER(email) = ?", [strtolower($email)]);
                    $name = $user ? "{$user['name']} {$user['surname']}" : 'Member';
                    $baseUrl = \Convota\App::discoverBaseUrl();
                    $userId = $user ? (int)$user['id'] : null;
                    $orgId = ($user && $user['organization_id']) ? (int)$user['organization_id'] : null;
                    $this->app->getMailer()->sendPasswordReset($email, $name, $token, $baseUrl, $userId, $orgId);
                }
            }
            // Always show friendly message to prevent email enumeration
            $this->app->setFlash('info', 'If an active account exists for that email, a password reset link has been dispatched.');
            redirect('/login');
        }

        $this->app->render('auth/forgot_password', [], 'auth_layout');
    }

    /**
     * Reset password using token
     */
    public function resetPassword(): void
    {
        $token = $_GET['token'] ?? ($_POST['token'] ?? '');
        if (empty($token)) {
            $this->app->setFlash('danger', 'Invalid or missing password reset token.');
            redirect('/login');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (strlen($password) < 8) {
                $this->app->setFlash('danger', 'Password must be at least 8 characters long.');
                $this->app->render('auth/reset_password', ['token' => $token], 'auth_layout');
                return;
            }

            if ($password !== $passwordConfirm) {
                $this->app->setFlash('danger', 'Passwords do not match.');
                $this->app->render('auth/reset_password', ['token' => $token], 'auth_layout');
                return;
            }

            $success = $this->app->getAuth()->resetPasswordWithToken($token, $password);
            if (!$success) {
                $this->app->setFlash('danger', 'This password reset link has expired or is invalid.');
                redirect('/login');
            }

            $this->app->setFlash('success', 'Your password has been successfully updated. You may now sign in.');
            redirect('/login');
        }

        $this->app->render('auth/reset_password', ['token' => $token], 'auth_layout');
    }

    /**
     * Terminate user session
     */
    public function logout(): void
    {
        $this->app->getAuth()->logout();
        unset($_SESSION['convota_org_auth']);
        $this->app->setFlash('info', 'You have been safely signed out.');
        redirect('/login');
    }
}

