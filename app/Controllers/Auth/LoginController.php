<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Validator;

class LoginController extends Controller
{
    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect('/super-admin/dashboard');
        }
        $this->render('auth.login', ['title' => 'Sign In — CodeGurukul LMS']);
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request, [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $remember = (bool)$request->input('remember', false);

        if (Auth::attempt($data['email'], $data['password'], $remember)) {
            $user = Auth::user();

            if (!in_array($user['role_slug'] ?? '', ['super_admin', 'admin'])) {
                Auth::logout();
                $this->withFlash('error', 'Access denied. Super Admin portal only.');
                $this->redirect('/login');
            }

            $intended = Session::get('intended_url', '/super-admin/dashboard');
            Session::remove('intended_url');

            $this->withFlash('success', "Welcome back, {$user['first_name']}!");
            $this->redirect($intended);
        } else {
            $this->withFlash('error', Session::getFlash('error') ?? 'Invalid email or password. Please try again.');
            $this->redirect('/login');
        }
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $this->withFlash('success', 'You have been signed out successfully.');
        $this->redirect('/login');
    }
}
